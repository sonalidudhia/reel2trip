<?php

use App\Filament\Resources\Reels\Pages\ListReels;
use App\Models\Reel;
use App\Models\Trip;
use App\Models\User;
use Livewire\Livewire;

function makeReels(User $user, int $count): array
{
    $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip']);

    return collect(range(1, $count))
        ->map(fn (int $i) => $trip->reels()->create([
            'url' => "https://instagram.com/reel/reel{$i}",
            'shortcode' => "reel{$i}",
            'status' => Reel::STATUS_DONE,
            // A pasted batch lands in the same second.
            'created_at' => '2026-07-30 10:00:00',
            'updated_at' => '2026-07-30 10:00:00',
        ]))
        ->all();
}

test('newest reels come first even when a whole batch shares a timestamp', function () {
    $user = User::factory()->create();
    [$first, $second, $third] = makeReels($user, 3);
    $this->actingAs($user);

    Livewire::test(ListReels::class)
        ->assertCanSeeTableRecords([$third, $second, $first], inOrder: true);
});

test('places count is not sortable, added is', function () {
    $this->actingAs(User::factory()->create());

    $table = Livewire::test(ListReels::class)->instance()->getTable();
    $sortable = collect($table->getColumns())
        ->filter(fn ($column) => $column->isSortable())
        ->keys();

    expect($sortable->all())->toBe(['created_at'])
        ->and($table->getDefaultSortColumn())->toBe('created_at')
        ->and($table->getDefaultSortDirection())->toBe('desc');
});
