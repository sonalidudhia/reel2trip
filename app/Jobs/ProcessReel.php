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

        foreach ($extractor->extract($reel->refresh()) as $extracted) {
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
