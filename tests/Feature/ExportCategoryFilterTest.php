<?php

use App\Exports\ExportablePlaces;
use App\Exports\GoogleEarthKml;
use App\Exports\GoogleMyMapsCsv;
use App\Filament\Pages\VisitingPlaces;
use App\Models\User;
use App\Support\PlaceCategories;
use Livewire\Livewire;

function csvNamesFor(User $user, ?int $cityId, ?string $category): array
{
    $response = (new GoogleMyMapsCsv(new ExportablePlaces($user->id, $cityId, $category)))->response();

    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    $lines = array_filter(explode("\n", trim(str_replace(GoogleMyMapsCsv::BYTE_ORDER_MARK, '', $csv))));

    return array_map(fn (string $line) => str_getcsv(trim($line, "\r"))[0], array_slice($lines, 1));
}

function seedMixedCategories(User $user): array
{
    [, $city, $reel] = makeExportTrip($user);

    $reel->places()->create(['name' => 'Bar Cañete', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 41.3, 'lng' => 2.1]);
    $reel->places()->create(['name' => 'Mala Leche', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true]);
    $reel->places()->create(['name' => 'Sagrada Familia', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 41.4, 'lng' => 2.2]);
    $reel->places()->create(['name' => 'Bunkers', 'category' => 'viewpoint', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 41.5, 'lng' => 2.3]);
    $reel->places()->create(['name' => 'Validate your ticket', 'category' => 'tip', 'trip_city_id' => $city->id, 'selected' => true]);

    return [$city, $reel];
}

test('the csv can be narrowed to one category', function () {
    $user = User::factory()->create();
    [$city] = seedMixedCategories($user);

    expect(csvNamesFor($user, $city->id, 'food'))->toBe(['Bar Cañete', 'Mala Leche']);
});

test('no category chosen still exports every category but never tips', function () {
    $user = User::factory()->create();
    [$city] = seedMixedCategories($user);

    $names = csvNamesFor($user, $city->id, null);

    expect($names)->toHaveCount(4)
        ->and($names)->not->toContain('Validate your ticket');
});

test('the kml honours the same category filter', function () {
    $user = User::factory()->create();
    [$city] = seedMixedCategories($user);

    $document = (new GoogleEarthKml(new ExportablePlaces($user->id, $city->id, 'food')))->document();

    expect($document)->toContain('Bar Cañete')
        ->not->toContain('Sagrada Familia')
        ->not->toContain('Bunkers');
});

test('the filename names the category when one is chosen', function () {
    $user = User::factory()->create();
    [$city] = seedMixedCategories($user);
    $today = now()->format('Y-m-d');

    expect((new ExportablePlaces($user->id, $city->id, 'food'))->filename('csv'))
        ->toBe("reel2trip-barcelona-food-{$today}.csv")
        ->and((new ExportablePlaces($user->id, null, 'food'))->filename('kml'))
        ->toBe("reel2trip-all-food-{$today}.kml")
        ->and((new ExportablePlaces($user->id, $city->id))->filename('csv'))
        ->toBe("reel2trip-barcelona-{$today}.csv");
});

test('the category picker never offers tips, since exports exclude them', function () {
    expect(PlaceCategories::optionsExcludingTips())
        ->toHaveKey('food')
        ->toHaveKey('sight')
        ->not->toHaveKey('tip');
});

test('the page passes the chosen category through to the download', function () {
    $user = User::factory()->create();
    [$city] = seedMixedCategories($user);
    $this->actingAs($user);

    $page = Livewire::test(VisitingPlaces::class)->instance();

    expect($page->exportCsv($city->id, 'food')->headers->get('content-disposition'))
        ->toContain('reel2trip-barcelona-food-')
        ->and($page->exportKml($city->id, 'food')->headers->get('content-disposition'))
        ->toContain('reel2trip-barcelona-food-');
});
