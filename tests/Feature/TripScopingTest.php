<?php

use App\Filament\Resources\Places\PlaceResource;
use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Reel;
use App\Models\Trip;
use App\Models\User;

function makeTripWithReelAndPlace(User $user): array
{
    $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip']);
    $reel = $trip->reels()->create([
        'url' => 'https://instagram.com/reel/' . uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_DONE,
    ]);
    $place = $reel->places()->create(['name' => 'Some Place', 'category' => 'sight']);

    return [$trip, $reel, $place];
}

test('a user cannot see another user\'s trips', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    [$tripA] = makeTripWithReelAndPlace($userA);
    [$tripB] = makeTripWithReelAndPlace($userB);

    $this->actingAs($userA);

    $visibleIds = TripResource::getEloquentQuery()->pluck('id');

    expect($visibleIds)->toContain($tripA->id)
        ->and($visibleIds)->not->toContain($tripB->id);
});

test('a user cannot see another user\'s reels', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    [, $reelA] = makeTripWithReelAndPlace($userA);
    [, $reelB] = makeTripWithReelAndPlace($userB);

    $this->actingAs($userA);

    $visibleIds = ReelResource::getEloquentQuery()->pluck('id');

    expect($visibleIds)->toContain($reelA->id)
        ->and($visibleIds)->not->toContain($reelB->id);
});

test('a user cannot see another user\'s places', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    [, , $placeA] = makeTripWithReelAndPlace($userA);
    [, , $placeB] = makeTripWithReelAndPlace($userB);

    $this->actingAs($userA);

    $visibleIds = PlaceResource::getEloquentQuery()->pluck('id');

    expect($visibleIds)->toContain($placeA->id)
        ->and($visibleIds)->not->toContain($placeB->id);
});
