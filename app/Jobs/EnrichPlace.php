<?php

namespace App\Jobs;

use App\Models\Place;
use App\Services\Geocoding\Geocoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichPlace implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public Place $place) {}

    public function handle(Geocoder $geocoder): void
    {
        $result = $geocoder->geocode($this->place);

        if ($result === null) {
            return; // leave unenriched; surface these in the UI for manual fixing
        }

        // array_filter so a Nominatim result (lat/lng/address only) doesn't
        // null out rating/price/hours a prior Google-driven enrichment set.
        $this->place->update(array_filter([
            'google_place_id' => $result->googlePlaceId,
            'lat' => $result->lat,
            'lng' => $result->lng,
            'address' => $result->address,
            'rating' => $result->rating,
            'price_level' => $result->priceLevel,
            'opening_hours' => $result->openingHours,
        ], fn ($value) => $value !== null));
    }
}
