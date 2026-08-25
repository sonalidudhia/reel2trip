<?php

use App\Models\Place;

function makeMappablePlace(array $attributes = []): Place
{
    return new Place(array_merge([
        'name' => 'Casa Batlló',
        'category' => 'sight',
        'lat' => 41.3917,
        'lng' => 2.1650,
    ], $attributes));
}

test('a place with a google place id links by that id', function () {
    $url = makeMappablePlace(['google_place_id' => 'ChIJ8zSvWrmipBIR'])->mapsUrl('Barcelona');

    expect($url)->toContain('query_place_id=ChIJ8zSvWrmipBIR')
        ->and($url)->toContain('api=1')
        ->and($url)->toContain(urlencode('Casa Batlló'))
        ->and($url)->not->toContain('Barcelona');
});

test('a place without a google place id searches on name and city', function () {
    $url = makeMappablePlace()->mapsUrl('Barcelona');

    expect($url)->toContain(urlencode('Casa Batlló, Barcelona'))
        ->and($url)->not->toContain('query_place_id');
});

test('a tip has no maps link', function () {
    expect(makeMappablePlace(['category' => 'tip'])->mapsUrl('Barcelona'))->toBeNull();
});

test('a place with neither coordinates nor a place id has no maps link', function () {
    expect(makeMappablePlace(['lat' => null, 'lng' => null])->mapsUrl('Barcelona'))->toBeNull();
});

test('a place with a place id but no coordinates still links', function () {
    expect(makeMappablePlace(['lat' => null, 'lng' => null, 'google_place_id' => 'ChIJabc'])->mapsUrl('Barcelona'))
        ->toContain('query_place_id=ChIJabc');
});

test('a place with no city falls back to the bare name', function () {
    expect(makeMappablePlace()->mapsUrl())->toContain('query='.urlencode('Casa Batlló'));
});
