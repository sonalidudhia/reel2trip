<?php

use App\Models\Place;
use App\Models\Reel;
use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Support\Facades\Http;

function makePlace(array $attributes): Place
{
    return Reel::create([
        'url' => 'https://instagram.com/reel/'.uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_DONE,
    ])->places()->create($attributes);
}

test('maps a successful response to a GeocodeResult and sets the User-Agent header', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '36.7213', 'lon' => '-4.4214', 'display_name' => 'Alcazaba, Malaga, Spain'],
        ]),
    ]);

    $place = makePlace(['name' => 'Alcazaba', 'city_guess' => 'Malaga', 'category' => 'sight']);

    $result = (new NominatimGeocoder)->geocode($place);

    expect($result->lat)->toBe(36.7213)
        ->and($result->lng)->toBe(-4.4214)
        ->and($result->address)->toBe('Alcazaba, Malaga, Spain');

    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent') && str_contains($request->header('User-Agent')[0], 'reel2trip'));
});

test('returns null when nominatim has no results', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

    $place = makePlace(['name' => 'Nonexistent Place Xyz', 'category' => 'sight']);

    expect((new NominatimGeocoder)->geocode($place))->toBeNull();
});

test('skips places categorized as a tip without making a request', function () {
    Http::fake();

    $place = makePlace(['name' => 'Validate your metro ticket', 'category' => 'tip']);

    expect((new NominatimGeocoder)->geocode($place))->toBeNull();

    Http::assertNothingSent();
});
