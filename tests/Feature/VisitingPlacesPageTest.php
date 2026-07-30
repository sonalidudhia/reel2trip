<?php

use App\Filament\Pages\VisitingPlaces;
use App\Models\Place;
use App\Models\Reel;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function makeSelectedPlace(User $user, array $placeAttrs = [], array $cityAttrs = []): array
{
    $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip']);
    $city = $trip->tripCities()->create(array_merge([
        'name' => 'City',
        'country' => 'Country',
        'days' => 1,
        'position' => 0,
    ], $cityAttrs));
    $reel = $trip->reels()->create([
        'url' => 'https://instagram.com/reel/'.uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_DONE,
    ]);
    $place = $reel->places()->create(array_merge([
        'name' => 'Some Place',
        'category' => 'sight',
        'trip_city_id' => $city->id,
        'selected' => true,
    ], $placeAttrs));

    return [$trip, $city, $place];
}

/** @return array<int, string> every place name the page would render */
function renderedPlaceNames(VisitingPlaces $page): array
{
    return collect($page->cities)
        ->flatMap(fn (array $city) => collect($city['groups'])->flatMap(fn (array $group) => $group['places']->pluck('name')))
        ->all();
}

test('only shows the authenticated user\'s selected places', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    [, , $placeA] = makeSelectedPlace($userA, ['name' => 'Mine']);
    makeSelectedPlace($userB, ['name' => 'Theirs']);
    $this->actingAs($userA);

    $page = Livewire::test(VisitingPlaces::class)->instance();

    expect(renderedPlaceNames($page))->toBe([$placeA->name]);
});

test('places that are not selected are excluded', function () {
    $user = User::factory()->create();
    [$trip, $city] = makeSelectedPlace($user, ['name' => 'Visiting']);
    $trip->reels()->first()->places()->create([
        'name' => 'Not visiting',
        'category' => 'sight',
        'trip_city_id' => $city->id,
        'selected' => false,
    ]);
    $this->actingAs($user);

    $page = Livewire::test(VisitingPlaces::class)->instance();

    expect(renderedPlaceNames($page))->toBe(['Visiting']);
});

test('groups a city\'s places by category, in planning order', function () {
    $user = User::factory()->create();
    [$trip, $city] = makeSelectedPlace($user, ['name' => 'Sagrada Familia', 'category' => 'sight'], ['name' => 'Barcelona']);
    $reel = $trip->reels()->first();
    $reel->places()->create(['name' => 'Bar Cañete', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true]);
    $reel->places()->create(['name' => 'Bunkers del Carmel', 'category' => 'viewpoint', 'trip_city_id' => $city->id, 'selected' => true]);
    $this->actingAs($user);

    $cities = Livewire::test(VisitingPlaces::class)->instance()->cities;

    expect($cities)->toHaveCount(1)
        ->and($cities[0]['name'])->toBe('Barcelona')
        ->and($cities[0]['count'])->toBe(3)
        ->and(collect($cities[0]['groups'])->pluck('label')->all())
        ->toBe(['Attractions', 'Food & Drink', 'Viewpoints'])
        ->and($cities[0]['groups'][1]['places']->pluck('name')->all())->toBe(['Bar Cañete']);
});

test('renders city, category headings and per-place detail', function () {
    $user = User::factory()->create();
    [$trip, $city] = makeSelectedPlace(
        $user,
        ['name' => 'Sagrada Familia', 'category' => 'sight', 'must_do' => true],
        ['name' => 'Barcelona', 'country' => 'Spain', 'days' => 3],
    );
    $trip->reels()->first()->places()->create([
        'name' => 'Bar Cañete', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true, 'tip' => 'go early',
    ]);
    $this->actingAs($user);

    Livewire::test(VisitingPlaces::class)
        ->assertSee('Barcelona')
        ->assertSee('3 days')
        ->assertSee('Attractions')
        ->assertSee('Food &amp; Drink', escape: false)
        ->assertSee('Sagrada Familia')
        ->assertSee('go early')
        ->assertSee('Must do')
        ->assertSee('Day 3'); // the day picker offers every day the city has
});

test('grouping by day lists every day of the city plus an unscheduled bucket', function () {
    $user = User::factory()->create();
    [$trip, $city] = makeSelectedPlace($user, ['name' => 'Park Guell', 'planned_day' => 2], ['name' => 'Barcelona', 'days' => 2]);
    $trip->reels()->first()->places()->create([
        'name' => 'Somewhere unplanned', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true,
    ]);
    $this->actingAs($user);

    $cities = Livewire::test(VisitingPlaces::class)->set('groupBy', 'day')->instance()->cities;

    expect(collect($cities[0]['groups'])->pluck('label')->all())->toBe(['Day 1', 'Day 2', 'Not scheduled yet'])
        ->and($cities[0]['groups'][0]['places'])->toHaveCount(0)
        ->and($cities[0]['groups'][1]['places']->pluck('name')->all())->toBe(['Park Guell'])
        ->and($cities[0]['groups'][2]['places']->pluck('name')->all())->toBe(['Somewhere unplanned']);
});

test('must-do filter narrows the list', function () {
    $user = User::factory()->create();
    [$trip, $city] = makeSelectedPlace($user, ['name' => 'Must see', 'must_do' => true]);
    $trip->reels()->first()->places()->create([
        'name' => 'Maybe', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true,
    ]);
    $this->actingAs($user);

    $page = Livewire::test(VisitingPlaces::class)->set('mustDoOnly', true)->instance();

    expect(renderedPlaceNames($page))->toBe(['Must see']);
});

test('assigning a day and toggling must-do persist', function () {
    $user = User::factory()->create();
    [, , $place] = makeSelectedPlace($user, [], ['days' => 3]);
    $this->actingAs($user);

    Livewire::test(VisitingPlaces::class)
        ->call('setDay', $place->id, '3')
        ->call('toggleMustDo', $place->id);

    expect($place->refresh()->planned_day)->toBe(3)
        ->and($place->must_do)->toBeTrue();
});

test('a place belonging to another user cannot be edited through the page', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    [, , $theirPlace] = makeSelectedPlace($userB);
    $this->actingAs($userA);

    expect(fn () => Livewire::test(VisitingPlaces::class)->call('setDay', $theirPlace->id, '2'))
        ->toThrow(ModelNotFoundException::class);

    expect($theirPlace->refresh()->planned_day)->toBeNull();
});

test('removing a place drops it off the list', function () {
    $user = User::factory()->create();
    [, , $place] = makeSelectedPlace($user);
    $this->actingAs($user);

    Livewire::test(VisitingPlaces::class)->call('unselect', $place->id);

    expect($place->refresh()->selected)->toBeFalse()
        ->and(Place::find($place->id))->not->toBeNull();
});
