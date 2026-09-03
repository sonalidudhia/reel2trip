<?php

use App\Filament\Resources\Places\Pages\ListPlaces;
use App\Filament\Resources\Reels\Pages\ListReels;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

test('the From reel column links to Instagram, not to the internal reel page', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $place = $reel->places()->create(['name' => 'Mala Leche', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true]);
    $this->actingAs($user);

    $column = Livewire::test(ListPlaces::class)
        ->instance()
        ->getTable()
        ->getColumn('reel.shortcode')
        ->record($place);

    expect($column->getUrl())->toBe($reel->url)
        ->and($column->getUrl())->toContain('instagram.com')
        ->and($column->shouldOpenUrlInNewTab())->toBeTrue();
});

test('a place whose reel was deleted has no link rather than a broken one', function () {
    $user = User::factory()->create();
    [, $city, $reel] = makeExportTrip($user);
    $place = $reel->places()->create(['name' => 'Orphan', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true]);
    $place->reel()->dissociate();
    $this->actingAs($user);

    $column = Livewire::test(ListPlaces::class)
        ->instance()
        ->getTable()
        ->getColumn('reel.shortcode')
        ->record($place);

    expect($column->getUrl())->toBeNull();
});

test('the reels table still reaches the transcript page', function () {
    $user = User::factory()->create();
    [, , $reel] = makeExportTrip($user);
    $this->actingAs($user);

    Livewire::test(ListReels::class)
        ->assertActionVisible(TestAction::make('view')->table($reel));
});
