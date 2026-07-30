<?php

use App\Filament\Resources\Places\Pages\ListPlaces;
use App\Models\User;
use Livewire\Livewire;

test('tables default to 50 rows per page', function () {
    $this->actingAs(User::factory()->create());

    $table = Livewire::test(ListPlaces::class)->instance()->getTable();

    expect($table->getDefaultPaginationPageOption())->toBe(50)
        ->and($table->getPaginationPageOptions())->toBe([25, 50, 100, 'all']);
});
