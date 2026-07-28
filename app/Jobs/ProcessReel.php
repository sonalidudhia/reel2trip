<?php

namespace App\Jobs;

use App\Ai\Agents\ReelPlaceExtractor;
use App\Models\Reel;
use App\Models\TripCity;
use App\Services\InstagramDownloader;
use App\Services\WhisperTranscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * The whole pipeline for one reel:
 * download -> transcribe -> extract -> save places -> dispatch enrichment.
 *
 * Kept as a single job (rather than a chain) so a retry re-runs the whole
 * thing idempotently — each stage skips work that's already done.
 */
class ProcessReel implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Backoff between retries — IG rate limits are the usual failure.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public Reel $reel) {}

    public function handle(
        InstagramDownloader $downloader,
        WhisperTranscriber $transcriber,
        ReelPlaceExtractor $extractor,
    ): void {
        $reel = $this->reel;

        if (! $reel->video_path) {
            $reel->update(['status' => Reel::STATUS_DOWNLOADING]);
            $downloader->download($reel->refresh());
        }

        if (! $reel->transcript) {
            $reel->update(['status' => Reel::STATUS_TRANSCRIBING]);
            $transcriber->transcribe($reel->refresh());
        }

        $reel->update(['status' => Reel::STATUS_EXTRACTING]);

        // Idempotency: wipe previous extraction results on retry
        $reel->places()->delete();

        $extractedPlaces = $this->backfillMissingCityGuess($extractor->extract($reel->refresh()));

        foreach ($extractedPlaces as $extracted) {
            $place = $reel->places()->create([
                'name' => $extracted['name'],
                'city_guess' => $extracted['city_guess'] ?? null,
                'category' => $extracted['category'] ?? 'sight',
                'description' => $extracted['description'] ?? null,
                'tip' => $extracted['tip'] ?? null,
                'price_hint' => $extracted['price_hint'] ?? null,
                'trip_city_id' => $this->matchCity($extracted['city_guess'] ?? null)?->id,
            ]);

            EnrichPlace::dispatch($place);
        }

        $reel->update(['status' => Reel::STATUS_DONE, 'error' => null]);
    }

    public function failed(Throwable $e): void
    {
        $this->reel->update([
            'status' => Reel::STATUS_FAILED,
            'error' => Str::limit($e->getMessage(), 500),
        ]);
    }

    /**
     * List-style reels ("12 Must-Visit Spots in Lisbon") name the city once in
     * the caption/title, so the model sometimes tags it on some entries and
     * drops it on others in the same list. Since every place from one reel is
     * almost always in the same city, fill nulls with that reel's majority
     * city_guess — never overrides an entry that already names a (possibly
     * different) city, so a reel that genuinely covers multiple cities is
     * left alone.
     *
     * @param  array<int, array<string, mixed>>  $extractedPlaces
     * @return array<int, array<string, mixed>>
     */
    private function backfillMissingCityGuess(array $extractedPlaces): array
    {
        $guesses = collect($extractedPlaces)->pluck('city_guess')->filter();

        if ($guesses->isEmpty()) {
            return $extractedPlaces;
        }

        $majorityGuess = $guesses->countBy()->sortDesc()->keys()->first();

        return array_map(function (array $extracted) use ($majorityGuess) {
            $extracted['city_guess'] ??= $majorityGuess;

            return $extracted;
        }, $extractedPlaces);
    }

    private function matchCity(?string $cityGuess): ?TripCity
    {
        if (! $cityGuess) {
            return null;
        }

        // Compare accent-folded (Str::ascii) so "Malaga" matches "Málaga".
        $needle = Str::lower(Str::ascii(trim($cityGuess)));

        return $this->reel->trip?->tripCities
            ->first(fn (TripCity $city) => Str::lower(Str::ascii($city->name)) === $needle);
    }
}
