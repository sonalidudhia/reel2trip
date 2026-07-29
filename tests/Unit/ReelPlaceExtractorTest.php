<?php

use App\Ai\Agents\ReelPlaceExtractor;
use App\Models\Reel;
use Illuminate\Support\Facades\Http;

function makeReelWithCaption(string $caption): Reel
{
    return Reel::create([
        'url' => 'https://instagram.com/reel/'.uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_DONE,
        'caption' => $caption,
    ]);
}

function fakeOllamaChat(array $body): void
{
    Http::fake([
        '*/api/chat' => Http::response([
            'message' => ['content' => json_encode($body)],
        ]),
    ]);
}

test('valid output is returned as-is', function () {
    fakeOllamaChat(['places' => [
        ['name' => 'Valid Place', 'category' => 'sight'],
    ]]);

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.'));

    expect($places)->toHaveCount(1)
        ->and($places[0]['name'])->toBe('Valid Place');
});

test('an entry missing a name is dropped without throwing', function () {
    fakeOllamaChat(['places' => [
        ['name' => 'Valid Place', 'category' => 'sight'],
        ['category' => 'food'], // missing name
    ]]);

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.'));

    expect($places)->toHaveCount(1)
        ->and($places[0]['name'])->toBe('Valid Place');
});

test('an entry with an invalid category is dropped without throwing', function () {
    fakeOllamaChat(['places' => [
        ['name' => 'Valid Place', 'category' => 'sight'],
        ['name' => 'Bad Category Place', 'category' => 'not-a-real-category'],
    ]]);

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.'));

    expect($places)->toHaveCount(1)
        ->and($places[0]['name'])->toBe('Valid Place');
});

test('an unparseable response retries once with the parse issue fed back', function () {
    Http::fakeSequence()
        ->push(['message' => ['content' => 'not valid json at all']])
        ->push(['message' => ['content' => json_encode(['places' => [
            ['name' => 'Retry Place', 'category' => 'sight'],
        ]])]]);

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.'));

    expect($places)->toHaveCount(1)
        ->and($places[0]['name'])->toBe('Retry Place');

    Http::assertSentCount(2);
});

test('throws when both attempts are unparseable', function () {
    Http::fake([
        '*/api/chat' => Http::response(['message' => ['content' => 'not valid json at all']]),
    ]);

    expect(fn () => (new ReelPlaceExtractor)->extract(makeReelWithCaption('Some caption.')))
        ->toThrow(RuntimeException::class);
});

test('returns an empty array without calling the model when the reel has no text', function () {
    Http::fake();

    $places = (new ReelPlaceExtractor)->extract(makeReelWithCaption(''));

    expect($places)->toBe([]);
    Http::assertNothingSent();
});
