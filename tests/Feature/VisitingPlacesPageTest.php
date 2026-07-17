<?php

use App\Filament\Pages\VisitingPlaces;
use App\Models\Reel;
use App\Models\Trip;
use App\Models\User;
use Livewire\Livewire;

function makeSelectedPlace(User $user, array $placeAttrs = []): array
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
        'selected' => true,
    ], $placeAttrs));

    return [$trip, $city, $place];
}

test('only shows the authenticated user\'s selected places', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    [, , $placeA] = makeSelectedPlace($userA);
    [, , $placeB] = makeSelectedPlace($userB);
    $this->actingAs($userA);

    Livewire::test(VisitingPlaces::class)
        ->assertCanSeeTableRecords([$placeA])
        ->assertCanNotSeeTableRecords([$placeB]);
});

test('places that are not selected are excluded', function () {
    $user = User::factory()->create();
    [$trip, $city, $selectedPlace] = makeSelectedPlace($user);
    $notSelected = $trip->reels()->first()->places()->create([
        'name' => 'Not visiting',
        'category' => 'sight',
        'trip_city_id' => $city->id,
        'selected' => false,
    ]);
    $this->actingAs($user);

    Livewire::test(VisitingPlaces::class)
        ->assertCanSeeTableRecords([$selectedPlace])
        ->assertCanNotSeeTableRecords([$notSelected]);
});
