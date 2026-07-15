<?php

use App\Ai\Agents\ReelPlaceExtractor;
use App\Models\Reel;
use Illuminate\Support\Facades\Http;

function makeReelWithCaption(string $caption): Reel
{
    return Reel::create([
        'url' => 'https://instagram.com/reel/' . uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_DONE,
        'caption' => $caption,
    ]);
}

test('valid structured output is returned as-is', function () {
    ReelPlaceExtractor::fake([
        ['places' => [
            ['name' => 'La Tranca', 'category' => 'food', 'city_guess' => 'Malaga'],
        ]],
    ]);

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption('Try La Tranca in Malaga.'));

    expect($places)->toHaveCount(1)
        ->and($places[0]['name'])->toBe('La Tranca');
});

test('an entry missing a name is dropped without throwing', function () {
    ReelPlaceExtractor::fake([
        ['places' => [
            ['name' => 'Valid Place', 'category' => 'sight'],
            ['category' => 'food'], // missing name
        ]],
    ]);

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.'));

    expect($places)->toHaveCount(1)
        ->and($places[0]['name'])->toBe('Valid Place');
});

test('an entry with an invalid category is dropped without throwing', function () {
    ReelPlaceExtractor::fake([
        ['places' => [
            ['name' => 'Valid Place', 'category' => 'sight'],
            ['name' => 'Bad Category Place', 'category' => 'not-a-real-category'],
        ]],
    ]);

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.'));

    expect($places)->toHaveCount(1)
        ->and($places[0]['name'])->toBe('Valid Place');
});

test('a structured response missing the places key falls back to a raw JSON retry', function () {
    // Structured path returns something unusable (no "places" key)...
    ReelPlaceExtractor::fake([['not' => 'the right shape']]);

    // ...so the fallback shells out directly to Ollama; fake that call to succeed on the first retry.
    Http::fake([
        '*/api/chat' => Http::response([
            'message' => ['content' => json_encode(['places' => [
                ['name' => 'Fallback Place', 'category' => 'sight'],
            ]])],
        ]),
    ]);

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.'));

    expect($places)->toHaveCount(1)
        ->and($places[0]['name'])->toBe('Fallback Place');
});

test('throws when both the structured call and both raw JSON attempts are unusable', function () {
    ReelPlaceExtractor::fake([['not' => 'the right shape']]);

    Http::fake([
        '*/api/chat' => Http::response(['message' => ['content' => 'not valid json at all']]),
    ]);

    expect(fn () => (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.')))
        ->toThrow(RuntimeException::class);
});

test('returns an empty array without calling the model when the reel has no text', function () {
    ReelPlaceExtractor::fake();

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption(''));

    expect($places)->toBe([]);
    ReelPlaceExtractor::assertNeverPrompted();
});
