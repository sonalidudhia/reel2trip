<?php

use App\Ai\Agents\ReelPlaceExtractor;
use App\Jobs\ProcessReel;
use App\Models\Reel;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // EnrichPlace also runs synchronously in tests; fake geocoding since
    // these tests are only about extraction-output city_guess backfill.
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);
});

function makeReelForBackfillTest(): Reel
{
    $user = User::factory()->create();
    $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip']);
    $trip->tripCities()->create(['name' => 'Lisbon', 'country' => 'Portugal', 'days' => 2, 'position' => 0]);

    return $trip->reels()->create([
        'url' => 'https://instagram.com/reel/'.uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_PENDING,
        'caption' => '12 Must-Visit Spots in Lisbon',
        // Skip the download/transcribe stages — this test is only about the
        // extraction-output backfill, not the pipeline's earlier steps.
        'video_path' => '/dev/null',
        'transcript' => 'placeholder',
    ]);
}

test('fills a missing city_guess from the reel\'s majority guess', function () {
    $reel = makeReelForBackfillTest();

    ReelPlaceExtractor::fake([
        ['places' => [
            ['name' => 'Santa Justa Lift', 'category' => 'viewpoint', 'city_guess' => null],
            ['name' => 'Rua Augusta Arch', 'category' => 'sight', 'city_guess' => null],
            ['name' => 'Rossio Square', 'category' => 'viewpoint', 'city_guess' => 'Lisbon'],
            ['name' => 'Miradouro de Santa Luzia', 'category' => 'viewpoint', 'city_guess' => 'Lisbon'],
        ]],
    ]);

    ProcessReel::dispatchSync($reel);

    $places = $reel->places()->pluck('city_guess', 'name');

    expect($places['Santa Justa Lift'])->toBe('Lisbon')
        ->and($places['Rua Augusta Arch'])->toBe('Lisbon')
        ->and($places['Rossio Square'])->toBe('Lisbon')
        ->and($places['Miradouro de Santa Luzia'])->toBe('Lisbon');
});

test('backfilled city_guess still matches to the right trip_city_id', function () {
    $reel = makeReelForBackfillTest();

    ReelPlaceExtractor::fake([
        ['places' => [
            ['name' => 'Santa Justa Lift', 'category' => 'viewpoint', 'city_guess' => null],
            ['name' => 'Rossio Square', 'category' => 'viewpoint', 'city_guess' => 'Lisbon'],
        ]],
    ]);

    ProcessReel::dispatchSync($reel);

    $lift = $reel->places()->where('name', 'Santa Justa Lift')->first();

    expect($lift->trip_city_id)->not->toBeNull()
        ->and($lift->tripCity->name)->toBe('Lisbon');
});

test('does not touch entries that already have a (possibly different) city_guess', function () {
    $reel = makeReelForBackfillTest();

    ReelPlaceExtractor::fake([
        ['places' => [
            ['name' => 'Rossio Square', 'category' => 'viewpoint', 'city_guess' => 'Lisbon'],
            ['name' => 'Rossio Square', 'category' => 'viewpoint', 'city_guess' => 'Lisbon'],
            ['name' => 'Ribeira Square', 'category' => 'viewpoint', 'city_guess' => 'Porto'],
        ]],
    ]);

    ProcessReel::dispatchSync($reel);

    expect($reel->places()->where('name', 'Ribeira Square')->first()->city_guess)->toBe('Porto');
});

test('leaves city_guess null when no entry in the reel has a guess at all', function () {
    $reel = makeReelForBackfillTest();

    ReelPlaceExtractor::fake([
        ['places' => [
            ['name' => 'Some Place', 'category' => 'sight', 'city_guess' => null],
        ]],
    ]);

    ProcessReel::dispatchSync($reel);

    expect($reel->places()->first()->city_guess)->toBeNull();
});
