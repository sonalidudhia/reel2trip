<?php

use App\Filament\Pages\VisitingPlaces;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function visibleNames(VisitingPlaces $page): array
{
    return collect($page->cities)
        ->flatMap(fn (array $city) => collect($city['groups'])->flatMap(fn (array $group) => $group['places']->pluck('name')))
        ->all();
}

function seedForCategoryFilter(User $user): array
{
    [, $city, $reel] = makeExportTrip($user);
    $reel->places()->create(['name' => 'Bar Cañete', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true]);
    $reel->places()->create(['name' => 'Mala Leche', 'category' => 'food', 'trip_city_id' => $city->id, 'selected' => true]);
    $reel->places()->create(['name' => 'Sagrada Familia', 'category' => 'sight', 'trip_city_id' => $city->id, 'selected' => true]);
    $reel->places()->create(['name' => 'Validate your ticket', 'category' => 'tip', 'trip_city_id' => $city->id, 'selected' => true]);

    return [$city, $reel];
}

test('the page can be filtered to food and drink', function () {
    $user = User::factory()->create();
    seedForCategoryFilter($user);
    $this->actingAs($user);

    $page = Livewire::test(VisitingPlaces::class)->set('category', 'food')->instance();

    expect(visibleNames($page))->toBe(['Bar Cañete', 'Mala Leche']);
});

test('no category shows everything, tips included', function () {
    $user = User::factory()->create();
    seedForCategoryFilter($user);
    $this->actingAs($user);

    $page = Livewire::test(VisitingPlaces::class)->instance();

    expect(visibleNames($page))->toHaveCount(4)
        ->and(visibleNames($page))->toContain('Validate your ticket');
});

test('the category survives a page reload through the query string', function () {
    $user = User::factory()->create();
    seedForCategoryFilter($user);
    $this->actingAs($user);

    Livewire::withQueryParams(['category' => 'food'])
        ->test(VisitingPlaces::class)
        ->assertSet('category', 'food');
});

test('the export dialog opens on the category the page is filtered to', function () {
    $user = User::factory()->create();
    seedForCategoryFilter($user);
    $this->actingAs($user);

    Livewire::test(VisitingPlaces::class)
        ->set('category', 'food')
        ->mountAction(TestAction::make('exportCsv'))
        ->assertActionDataSet(['category' => 'food']);
});
