<?php

use App\Exports\ExportablePlaces;
use App\Exports\GoogleEarthKml;
use App\Models\User;

function kmlFor(User $user, ?int $cityId = null): string
{
    return (new GoogleEarthKml(new ExportablePlaces($user->id, $cityId)))->document();
}

test('the document is valid xml with a folder per city', function () {
    $user = User::factory()->create();
    [, $barcelona, $reelA] = makeExportTrip($user);
    [, $madrid, $reelB] = makeExportTrip($user, 'Madrid');
    $reelA->places()->create(['name' => 'Park Guell', 'category' => 'sight', 'trip_city_id' => $barcelona->id, 'selected' => true, 'lat' => 41.4145, 'lng' => 2.1527]);
    $reelB->places()->create(['name' => 'Prado', 'category' => 'sight', 'trip_city_id' => $madrid->id, 'selected' => true, 'lat' => 40.4138, 'lng' => -3.6921]);

    $xml = new SimpleXMLElement(kmlFor($user));
    $folders = $xml->Document->Folder;

    expect(count($folders))->toBe(2)
        ->and((string) $folders[0]->name)->toBe('Barcelona')
        ->and((string) $folders[0]->Placemark[0]->name)->toBe('Park Guell')
        ->and((string) $folders[0]->Placemark[0]->Point->coordinates)->toBe('2.1527,41.4145,0');
});

test('a name with an ampersand and accents is escaped and survives a round trip', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create([
        'name' => 'Bar & Grill "Málaga" <tapas>',
        'category' => 'food',
        'trip_city_id' => $city->id,
        'selected' => true,
        'lat' => 36.7213,
        'lng' => -4.4214,
        'description' => 'Cheap & cheerful',
    ]);

    $document = kmlFor($user, $city->id);

    expect($document)->toContain('&amp;')
        ->and($document)->not->toContain('Bar & Grill');

    $xml = new SimpleXMLElement($document);

    expect((string) $xml->Document->Folder->Placemark->name)->toBe('Bar & Grill "Málaga" <tapas>');
});

test('the description carries the description, the tip and the reel url', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create([
        'name' => 'Mala Leche', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true,
        'lat' => 36.7, 'lng' => -4.4, 'description' => 'Brunch spot', 'tip' => 'Go before nine',
    ]);

    $description = (string) (new SimpleXMLElement(kmlFor($user, $city->id)))->Document->Folder->Placemark->description;

    expect($description)->toContain('Brunch spot')
        ->toContain('Go before nine')
        ->toContain($reel->url);
});

test('a place without coordinates is left out of the kml', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create(['name' => 'Has a pin', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 1, 'lng' => 2]);
    $reel->places()->create(['name' => 'No pin', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true]);

    $document = kmlFor($user, $city->id);

    expect($document)->toContain('Has a pin')->not->toContain('No pin');
});

test('tips and unselected places are left out, and another user sees nothing', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    [, $city, $reel] = makeExportTrip($owner);
    $reel->places()->create(['name' => 'A tip', 'category' => 'tip', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 1, 'lng' => 2]);
    $reel->places()->create(['name' => 'Not selected', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => false, 'lat' => 1, 'lng' => 2]);
    $reel->places()->create(['name' => 'Kept', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 1, 'lng' => 2]);

    expect(kmlFor($owner, $city->id))
        ->toContain('Kept')
        ->not->toContain('A tip')
        ->not->toContain('Not selected');

    expect(kmlFor($stranger, $city->id))->not->toContain('Kept');
});
