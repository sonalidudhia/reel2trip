<?php

use App\Http\Controllers\ReelController;
use App\Models\Place;
use App\Models\Reel;
use App\Models\TripCity;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $reels = Reel::latest()->withCount('places')->get();

    return view('dashboard', [
        'reels'       => $reels,
        'cities'      => TripCity::with(['places' => fn ($q) => $q->where('dismissed', false)])->get(),
        'unassigned'  => Place::whereNull('trip_city_id')->where('dismissed', false)->get(),
        'autoRefresh' => $reels->whereIn('status', [
            Reel::STATUS_PENDING, Reel::STATUS_DOWNLOADING,
            Reel::STATUS_TRANSCRIBING, Reel::STATUS_EXTRACTING,
        ])->isNotEmpty(),
    ]);
})->name('dashboard');

Route::post('/reels', [ReelController::class, 'store'])->name('reels.store');

// JSON endpoints (handy for debugging / future map view)
Route::get('/api/reels', fn () => Reel::latest()->with('places')->get());
Route::get('/api/cities/{tripCity}/places', fn (TripCity $tripCity) => $tripCity
    ->places()
    ->where('dismissed', false)
    ->orderBy('category')
    ->get()
);
