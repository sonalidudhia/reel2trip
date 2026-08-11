<?php

use App\Filament\Resources\Places\Pages\ListPlaces;
use App\Models\Reel;
use App\Models\Trip;
use App\Models\User;
use Livewire\Livewire;

test('the most recently extracted places are listed first', function () {
    $user = User::factory()->create();
    $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip']);
    $reel = $trip->reels()->create([
        'url' => 'https://instagram.com/reel/a',
        'shortcode' => 'a',
        'status' => Reel::STATUS_DONE,
    ]);

    $oldest = $reel->places()->create(['name' => 'Extracted first', 'category' => 'sight']);
    $newest = $reel->places()->create(['name' => 'Extracted last', 'category' => 'food']);
    $this->actingAs($user);

    Livewire::test(ListPlaces::class)->assertCanSeeTableRecords([$newest, $oldest], inOrder: true);
});
