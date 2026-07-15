<?php

namespace App\Services\Geocoding;

use App\Models\Place;
use Illuminate\Support\Facades\Http;

/**
 * Resolves an extracted place name to a real location via the
 * Google Places API (Text Search + Details).
 */
class GooglePlacesGeocoder implements Geocoder
{
    /** price_level string enum (New API) -> the 0-4 int this app's schema expects. */
    private const PRICE_LEVELS = [
        'PRICE_LEVEL_FREE'           => 0,
        'PRICE_LEVEL_INEXPENSIVE'    => 1,
        'PRICE_LEVEL_MODERATE'       => 2,
        'PRICE_LEVEL_EXPENSIVE'      => 3,
        'PRICE_LEVEL_VERY_EXPENSIVE' => 4,
    ];

    public function geocode(Place $place): ?GeocodeResult
    {
        if ($place->category === 'tip') {
            return null; // tips aren't physical places
        }

        $query = trim($place->name . ' ' . ($place->city_guess ?? ''));
        $key   = config('services.google.places_key');

        $searchResponse = Http::timeout(30)
            ->withHeaders([
                'X-Goog-Api-Key'   => $key,
                'X-Goog-FieldMask' => 'places.id',
            ])
            ->post('https://places.googleapis.com/v1/places:searchText', [
                'textQuery' => $query,
            ]);

        $searchResponse->throw();

        $candidate = $searchResponse->json('places.0');

        if (! $candidate) {
            return null; // leave unenriched; surface these in the UI for manual fixing
        }

        $detailsResponse = Http::timeout(30)
            ->withHeaders([
                'X-Goog-Api-Key'   => $key,
                'X-Goog-FieldMask' => 'location,rating,priceLevel,formattedAddress,regularOpeningHours',
            ])
            ->get("https://places.googleapis.com/v1/places/{$candidate['id']}");

        $detailsResponse->throw();

        $details = $detailsResponse->json();

        return new GeocodeResult(
            lat: $details['location']['latitude'] ?? 0.0,
            lng: $details['location']['longitude'] ?? 0.0,
            address: $details['formattedAddress'] ?? null,
            rating: $details['rating'] ?? null,
            priceLevel: self::PRICE_LEVELS[$details['priceLevel'] ?? ''] ?? null,
            openingHours: $details['regularOpeningHours']['weekdayDescriptions'] ?? null,
            googlePlaceId: $candidate['id'],
        );
    }
}
