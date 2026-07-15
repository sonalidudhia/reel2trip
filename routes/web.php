<?php

use App\Models\Reel;
use App\Models\TripCity;
use Illuminate\Support\Facades\Route;

// JSON endpoints (handy for debugging / future map view)
Route::get('/api/reels', fn () => Reel::latest()->with('places')->get());
Route::get('/api/cities/{tripCity}/places', fn (TripCity $tripCity) => $tripCity
    ->places()
    ->where('dismissed', false)
    ->orderBy('category')
    ->get()
);
