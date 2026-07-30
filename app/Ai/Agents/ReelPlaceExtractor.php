<?php

namespace App\Ai\Agents;

use App\Models\Reel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends all text pulled from a reel to a local Ollama model and gets back
 * structured place records. Free, no API key — runs on-device.
 *
 * Uses Ollama's plain JSON mode (format: "json") rather than laravel/ai's
 * schema-constrained structured output (dropped the Agent/Promptable setup
 * this used to have). Tested head-to-head on the same text with qwen2.5:7b:
 * the schema-constrained path returned city_guess null on every single
 * entry, while plain JSON mode correctly filled it on all of them — the
 * nullable-field constraint appears to bias this model/Ollama combination
 * toward null. If a response can't be parsed at all, one retry feeds the
 * parse error back to the model before giving up. Every place is validated
 * before being handed back; invalid entries are dropped and logged rather
 * than failing the whole extraction.
 */
class ReelPlaceExtractor
{
    private const CATEGORIES = ['food', 'sight', 'viewpoint', 'activity', 'area', 'tip'];

    /**
     * The model sometimes reaches for a more specific word than our 6-value
     * enum allows (e.g. "restaurant" instead of "food", "beach" instead of
     * "viewpoint"). Map those onto the closest real category instead of
     * dropping an otherwise well-extracted place over vocabulary mismatch.
     */
    private const CATEGORY_ALIASES = [
        'restaurant' => 'food',
        'cafe' => 'food',
        'bar' => 'food',
        'bakery' => 'food',
        'dessert' => 'food',
        'beach' => 'viewpoint',
        'cliff' => 'viewpoint',
        'sunset spot' => 'viewpoint',
        'island' => 'sight',
        'museum' => 'sight',
        'hike' => 'activity',
        'trail' => 'activity',
        'tour' => 'activity',
        'market' => 'area',
        'location' => 'area',
        'neighborhood' => 'area',
        'neighbourhood' => 'area',
        'street' => 'area',
        'town' => 'area',
    ];

    /** @return array<int, array<string, mixed>> */
    public function extract(Reel $reel): array
    {
        $text = $reel->combinedText();

        if (trim($text) === '') {
            return [];
        }

        $attempt = $this->promptOllamaForJson($text);

        if ($attempt === null) {
            $retryPrompt = $text."\n\n---\nYour previous response could not be parsed as the JSON object described above. "
                .'Respond again with ONLY that JSON object, no markdown fences, no preamble.';

            $attempt = $this->promptOllamaForJson($retryPrompt);

            if ($attempt === null) {
                throw new RuntimeException('Extractor returned unparseable output twice in a row.');
            }
        }

        return $this->validated($attempt);
    }

    /** @return array<int, array<string, mixed>>|null */
    private function promptOllamaForJson(string $userText): ?array
    {
        $response = Http::timeout(300)
            ->post(rtrim(config('services.ollama.base_url'), '/').'/api/chat', [
                'model' => config('services.ollama.model'),
                'stream' => false,
                'format' => 'json',
                // Ollama holds the weights resident for 5 minutes after a request
                // by default, which on a small-RAM machine keeps the whole system
                // swapping long after the reel is done. Send it home instead.
                'keep_alive' => config('services.ollama.keep_alive'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $userText],
                ],
            ]);

        $response->throw();

        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $response->json('message.content', '')));
        $decoded = json_decode($raw, true);

        return is_array($decoded) && isset($decoded['places']) && is_array($decoded['places'])
            ? $decoded['places']
            : null;
    }

    /**
     * Drop entries that don't match the schema instead of failing the whole extraction.
     *
     * @param  array<int, mixed>  $places
     * @return array<int, array<string, mixed>>
     */
    private function validated(array $places): array
    {
        $valid = [];

        foreach ($places as $place) {
            if (! is_array($place) || empty($place['name'])) {
                Log::warning('ReelPlaceExtractor dropped an invalid place entry', ['place' => $place]);

                continue;
            }

            $category = $this->normalizeCategory($place['category'] ?? null);

            if ($category === null) {
                Log::warning('ReelPlaceExtractor dropped a place with an unrecognized category', ['place' => $place]);

                continue;
            }

            $place['category'] = $category;
            $valid[] = $place;
        }

        return $valid;
    }

    /**
     * The model sometimes combines categories ("food | viewpoint") or reaches
     * for a synonym ("restaurant", "beach") instead of our exact enum. Take
     * the first term, lowercase it, and map it onto a real category rather
     * than rejecting the whole entry over a vocabulary mismatch.
     */
    private function normalizeCategory(mixed $category): ?string
    {
        if (! is_string($category) || trim($category) === '') {
            return null;
        }

        $first = strtolower(trim(explode('|', $category)[0]));

        if (in_array($first, self::CATEGORIES, true)) {
            return $first;
        }

        return self::CATEGORY_ALIASES[$first] ?? null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You extract travel places from Instagram reel text (captions, audio transcripts, on-screen text). The text is messy influencer-speak; that is expected.

        Respond with ONLY a JSON object, no markdown fences, no preamble:

        {
          "places": [
            {
              "name": "official/searchable place name, cleaned up",
              "city_guess": "city if stated or strongly implied, else null",
              "category": "food",
              "description": "one sentence: what it is and why it was recommended",
              "tip": "practical advice from the reel (timing, booking, cash only), else null",
              "price_hint": "any price mentioned, verbatim-ish, else null"
            }
          ]
        }

        Rules:
        - One entry per distinct place. A reel listing "5 must-eat spots in Porto" yields 5 entries.
        - "category" must be EXACTLY ONE of these six words, nothing else: food, sight, viewpoint, activity, area, tip. Never combine two of them and never invent a different word (not "restaurant", not "beach", not "museum") — pick whichever of the six fits best.
        - "name" must be Google-Maps-searchable: "Manteigaria" not "this pastel de nata place 😍". Write it in normal Title Case even when the on-screen text is ALL CAPS, and prefer the spoken/caption spelling over the on-screen one when they differ.
        - "tip" is the most useful field to a trip planner, so do not leave it null when the text contains advice: booking ahead, best time of day, queues, cash only, what to order, what to wear, how to get there. Attach the advice to the place it belongs to.
        - "description" is never null when the reel says anything at all about the place.
        - General advice with no place attached (e.g. "always validate metro tickets") gets category "tip" and name = short summary of the tip.
        - Tips still get a "city_guess" whenever the reel says or clearly implies which city the advice is for — e.g. "In Barcelona, always validate your metro ticket" is city_guess "Barcelona", not null. Only leave it null if the reel never says which city the tip applies to.
        - If the text contains no places or tips at all, return {"places": []}.
        - Never invent places that are not in the text.

        Worked example. Text: "PASTEIS DE BELEM / first stop Pasteis de Belem in Lisbon, go before 9am or you'll queue for an hour, they're like one euro fifty each" gives:

        {"places": [{"name": "Pasteis de Belem", "city_guess": "Lisbon", "category": "food", "description": "The original pastel de nata bakery, first stop of the reel.", "tip": "Go before 9am to avoid an hour-long queue.", "price_hint": "~€1.50 each"}]}
        PROMPT;
    }
}
