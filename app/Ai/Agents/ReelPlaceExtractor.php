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
            if (! is_array($place) || empty($place['name']) || ! in_array($place['category'] ?? null, self::CATEGORIES, true)) {
                Log::warning('ReelPlaceExtractor dropped an invalid place entry', ['place' => $place]);

                continue;
            }

            $valid[] = $place;
        }

        return $valid;
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
              "category": "food | sight | viewpoint | activity | area | tip",
              "description": "one sentence: what it is and why it was recommended",
              "tip": "practical advice from the reel (timing, booking, cash only), else null",
              "price_hint": "any price mentioned, verbatim-ish, else null"
            }
          ]
        }

        Rules:
        - One entry per distinct place. A reel listing "5 must-eat spots in Porto" yields 5 entries.
        - "name" must be Google-Maps-searchable: "Manteigaria" not "this pastel de nata place 😍".
        - General advice with no place attached (e.g. "always validate metro tickets") gets category "tip" and name = short summary of the tip.
        - Tips still get a "city_guess" whenever the reel says or clearly implies which city the advice is for — e.g. "In Barcelona, always validate your metro ticket" is city_guess "Barcelona", not null. Only leave it null if the reel never says which city the tip applies to.
        - If the text contains no places or tips at all, return {"places": []}.
        - Never invent places that are not in the text.
        PROMPT;
    }
}
