<?php

use App\Models\Place;
use App\Models\Reel;

function makePlaceForSelectionTest(array $attributes = []): Place
{
    return Reel::create([
        'url' => 'https://instagram.com/reel/'.uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_DONE,
    ])->places()->create(array_merge(['name' => 'Test Place', 'category' => 'sight'], $attributes));
}

test('must_do cannot be true while selected is false', function () {
    $place = makePlaceForSelectionTest(['selected' => false, 'must_do' => true]);

    expect($place->fresh()->must_do)->toBeFalse();
});

test('setting selected to false clears an existing must_do', function () {
    $place = makePlaceForSelectionTest(['selected' => true, 'must_do' => true]);
    expect($place->fresh()->must_do)->toBeTrue();

    $place->update(['selected' => false]);

    expect($place->fresh()->must_do)->toBeFalse();
});

test('must_do can be true when selected is true', function () {
    $place = makePlaceForSelectionTest(['selected' => true, 'must_do' => true]);

    expect($place->fresh()->must_do)->toBeTrue();
});
