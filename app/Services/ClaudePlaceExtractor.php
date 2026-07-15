<?php

namespace App\Services;

use App\Models\Reel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends all text pulled from a reel to a local Ollama model and gets back
 * structured place records. Free, no API key — runs on-device.
 */
class ClaudePlaceExtractor
{
    public function extract(Reel $reel): array
    {
        $text = $reel->combinedText();

        if (trim($text) === '') {
            return [];
        }

        $response = Http::timeout(120)
            ->post(rtrim(config('services.ollama.base_url'), '/') . '/api/chat', [
                'model'    => config('services.ollama.model'),
                'stream'   => false,
                'format'   => 'json',
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

        $response->throw();

        $raw = $response->json('message.content', '');

        // Strip markdown fences if the model added them anyway
        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw));

        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['places'])) {
            throw new RuntimeException('Extractor returned unparseable output: ' . mb_substr($raw, 0, 300));
        }

        return $decoded['places'];
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
- If the text contains no places or tips at all, return {"places": []}.
- Never invent places that are not in the text.
PROMPT;
    }
}
