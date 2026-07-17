<?php

use App\Filament\Pages\MyPlaces;
use App\Models\Reel;
use App\Models\Trip;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function makeTripWithCityAndPlace(User $user, array $placeAttrs = []): array
{
    $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip']);
    $city = $trip->tripCities()->create(['name' => 'City', 'country' => 'Country', 'days' => 1, 'position' => 0]);
    $reel = $trip->reels()->create([
        'url' => 'https://instagram.com/reel/'.uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_DONE,
    ]);
    $place = $reel->places()->create(array_merge([
        'name' => 'Some Place',
        'category' => 'sight',
        'trip_city_id' => $city->id,
    ], $placeAttrs));

    return [$trip, $city, $reel, $place];
}

function mountMyPlaces(Trip $trip): MyPlaces
{
    $page = new MyPlaces;
    $page->mount($trip);

    return $page;
}

test('toggling selected persists without a page reload', function () {
    $user = User::factory()->create();
    [$trip, , , $place] = makeTripWithCityAndPlace($user);
    $this->actingAs($user);

    mountMyPlaces($trip)->toggleSelected($place->id);

    expect($place->fresh()->selected)->toBeTrue();
});

test('toggling visiting off clears must_do', function () {
    $user = User::factory()->create();
    [$trip, , , $place] = makeTripWithCityAndPlace($user, ['selected' => true, 'must_do' => true]);
    $this->actingAs($user);

    mountMyPlaces($trip)->toggleSelected($place->id);

    $place->refresh();
    expect($place->selected)->toBeFalse()
        ->and($place->must_do)->toBeFalse();
});

test('must_do cannot be toggled on while not selected', function () {
    $user = User::factory()->create();
    [$trip, , , $place] = makeTripWithCityAndPlace($user, ['selected' => false]);
    $this->actingAs($user);

    mountMyPlaces($trip)->toggleMustDo($place->id);

    expect($place->fresh()->must_do)->toBeFalse();
});

test('dismissed and tip places are excluded from toggle rows, tips render separately', function () {
    $user = User::factory()->create();
    [$trip, $city] = makeTripWithCityAndPlace($user);
    $reel = $trip->reels()->first();

    $dismissed = $reel->places()->create(['name' => 'Dismissed Place', 'category' => 'sight', 'trip_city_id' => $city->id, 'dismissed' => true]);
    $tip = $reel->places()->create(['name' => 'A Tip', 'category' => 'tip', 'tip' => 'bring cash', 'trip_city_id' => $city->id]);
    $this->actingAs($user);

    $cityData = mountMyPlaces($trip)->getCountries()->first()['cities']->first();

    expect($cityData['places']->pluck('id'))->not->toContain($dismissed->id)
        ->and($cityData['places']->pluck('id'))->not->toContain($tip->id)
        ->and($cityData['tips']->pluck('id'))->toContain($tip->id);
});

test('filter tabs narrow results and counts are correct', function () {
    $user = User::factory()->create();
    [$trip, $city] = makeTripWithCityAndPlace($user, ['selected' => true, 'must_do' => true]);
    $reel = $trip->reels()->first();
    $reel->places()->create(['name' => 'Just visiting', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true]);
    $reel->places()->create(['name' => 'Not selected', 'category' => 'sight', 'trip_city_id' => $city->id]);
    $this->actingAs($user);

    $page = mountMyPlaces($trip);

    $counts = $page->counts($city->places);
    expect($counts['selected'])->toBe(2)
        ->and($counts['mustDo'])->toBe(1);

    $page->filter = 'must_do';
    $mustDoCities = $page->getCountries()->first()['cities']->first();
    expect($mustDoCities['places'])->toHaveCount(1);

    $page->filter = 'visiting';
    $visitingCities = $page->getCountries()->first()['cities']->first();
    expect($visitingCities['places'])->toHaveCount(2);
});

test('a user cannot load another user\'s trip planner page', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    [$tripB] = makeTripWithCityAndPlace($userB);
    $this->actingAs($userA);

    expect(fn () => mountMyPlaces($tripB))->toThrow(NotFoundHttpException::class);
});
