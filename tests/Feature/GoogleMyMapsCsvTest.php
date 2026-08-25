<?php

use App\Exports\ExportablePlaces;
use App\Exports\GoogleMyMapsCsv;
use App\Filament\Pages\VisitingPlaces;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

function csvFor(User $user, ?int $cityId = null): string
{
    $response = (new GoogleMyMapsCsv(new ExportablePlaces($user->id, $cityId)))->response();

    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function csvRows(string $csv): array
{
    $lines = array_filter(explode("\n", trim(str_replace(GoogleMyMapsCsv::BYTE_ORDER_MARK, '', $csv))));

    return array_map(fn (string $line) => str_getcsv(trim($line, "\r")), $lines);
}

test('exports only selected, non-dismissed, non-tip places for the chosen city', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    [, $otherCity] = makeExportTrip($user, 'Madrid');

    $reel->places()->create(['name' => 'Included', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 41.1, 'lng' => 2.1]);
    $reel->places()->create(['name' => 'Not selected', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => false]);
    $reel->places()->create(['name' => 'Dismissed', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'dismissed' => true]);
    $reel->places()->create(['name' => 'A tip', 'category' => 'tip', 'trip_city_id' => $city->id, 'selected' => true]);
    $reel->places()->create(['name' => 'Other city', 'category' => 'sight', 'trip_city_id' => $otherCity->id, 'selected' => true]);

    $rows = csvRows(csvFor($user, $city->id));

    expect($rows[0])->toBe(GoogleMyMapsCsv::HEADER)
        ->and(array_column(array_slice($rows, 1), 0))->toBe(['Included']);
});

test('the file starts with a byte order mark so Excel reads accents', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create(['name' => 'Casa Batlló', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 41.1, 'lng' => 2.1]);

    $csv = csvFor($user, $city->id);

    expect($csv)->toStartWith(GoogleMyMapsCsv::BYTE_ORDER_MARK)
        ->and($csv)->toContain('Casa Batlló');
});

test('a place without coordinates exports blank lat lng and a geocodable address', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create(['name' => 'Mala Leche', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true]);

    $row = csvRows(csvFor($user, $city->id))[1];

    expect($row[1])->toBe('')
        ->and($row[2])->toBe('')
        ->and($row[5])->toBe('Mala Leche, Barcelona, Spain');
});

test('must do renders as a label rather than a boolean', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create(['name' => 'A must', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'must_do' => true, 'lat' => 1, 'lng' => 2]);
    $reel->places()->create(['name' => 'B maybe', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 1, 'lng' => 2]);

    $rows = csvRows(csvFor($user, $city->id));
    $byName = collect(array_slice($rows, 1))->keyBy(0);

    expect($byName['A must'][6])->toBe('Must do')
        ->and($byName['B maybe'][6])->toBe('');
});

test('the reel url travels with each place', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create(['name' => 'Sourced', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 1, 'lng' => 2]);

    expect(csvRows(csvFor($user, $city->id))[1][7])->toBe($reel->url);
});

test('all cities exports every city of the user', function () {
    $user = User::factory()->create();
    [, $barcelona, $reelA] = makeExportTrip($user);
    [, $madrid, $reelB] = makeExportTrip($user, 'Madrid');
    $reelA->places()->create(['name' => 'In Barcelona', 'category' => 'sight', 'trip_city_id' => $barcelona->id, 'selected' => true]);
    $reelB->places()->create(['name' => 'In Madrid', 'category' => 'sight', 'trip_city_id' => $madrid->id, 'selected' => true]);

    $names = array_column(array_slice(csvRows(csvFor($user)), 1), 0);

    expect($names)->toContain('In Barcelona')->toContain('In Madrid');
});

test('another user\'s places are never exported', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    [, $city, $reel] = makeExportTrip($owner);
    $reel->places()->create(['name' => 'Private', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 1, 'lng' => 2]);

    $rows = csvRows(csvFor($stranger, $city->id));

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBe(GoogleMyMapsCsv::HEADER);
});

test('the filename names the city and the day', function () {
    $user = User::factory()->create();
    [, $city] = makeExportTrip($user);

    expect((new ExportablePlaces($user->id, $city->id))->filename('csv'))
        ->toBe('reel2trip-barcelona-'.now()->format('Y-m-d').'.csv')
        ->and((new ExportablePlaces($user->id))->filename('csv'))
        ->toBe('reel2trip-all-'.now()->format('Y-m-d').'.csv');
});

test('the page offers the export action and it streams a download', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create(['name' => 'Included', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true, 'lat' => 1, 'lng' => 2]);
    $this->actingAs($user);

    $page = Livewire::test(VisitingPlaces::class)->assertActionVisible(TestAction::make('exportCsv'));

    $response = $page->instance()->exportCsv($city->id);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-disposition'))
        ->toContain('reel2trip-barcelona-'.now()->format('Y-m-d').'.csv');
});

test('the city picker offers only the signed-in user\'s cities', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    [, $city] = makeExportTrip($owner);
    makeExportTrip($stranger, 'Lisbon');
    $this->actingAs($owner);

    $options = Livewire::test(VisitingPlaces::class)->instance()->exportCityOptions();

    expect($options)->toBe([$city->id => 'Barcelona']);
});
