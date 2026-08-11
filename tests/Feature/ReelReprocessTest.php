<?php

use App\Filament\Resources\Reels\Pages\ListReels;
use App\Jobs\ProcessReel;
use App\Models\Reel;
use App\Models\Trip;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function makeDoneReel(User $user): Reel
{
    $trip = Trip::create(['user_id' => $user->id, 'name' => 'Trip']);

    return $trip->reels()->create([
        'url' => 'https://www.instagram.com/p/DbOtxukNIgy/',
        'shortcode' => 'DbOtxukNIgy',
        'status' => Reel::STATUS_DONE,
    ]);
}

test('a finished reel can be sent through the pipeline again', function () {
    Queue::fake();
    $user = User::factory()->create();
    $reel = makeDoneReel($user);
    $this->actingAs($user);

    Livewire::test(ListReels::class)->callAction(TestAction::make('reprocess')->table($reel));

    Queue::assertPushed(ProcessReel::class);
    expect($reel->refresh()->status)->toBe(Reel::STATUS_PENDING);
});

test('re-adding a URL that is already here reports which one and queues nothing', function () {
    Queue::fake();
    $user = User::factory()->create();
    $reel = makeDoneReel($user);
    $this->actingAs($user);

    Livewire::test(ListReels::class)
        ->callAction(TestAction::make('addReels')->table(), [
            'trip_id' => $reel->trip_id,
            'urls' => 'https://www.instagram.com/p/DbOtxukNIgy/',
        ])
        ->assertNotified();

    Queue::assertNothingPushed();
    expect(Reel::count())->toBe(1);
});
