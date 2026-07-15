<?php

namespace App\Services\Geocoding;

use App\Models\Place;
use Illuminate\Support\Facades\Http;

/**
 * Free, keyless geocoding via OpenStreetMap's Nominatim. Fills lat/lng/address
 * only — no rating/price/hours, Nominatim doesn't have those.
 */
class NominatimGeocoder implements Geocoder
{
    public function geocode(Place $place): ?GeocodeResult
    {
        if ($place->category === 'tip') {
            return null; // tips aren't physical places
        }

        $this->throttle();

        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'reel2trip/1.0 (' . config('mail.from.address') . ')'])
            ->get('https://nominatim.openstreetmap.org/search', [
                'q'      => trim($place->name . ' ' . ($place->city_guess ?? '')),
                'format' => 'json',
                'limit'  => 1,
            ]);

        $response->throw();

        $hit = $response->json('0');

        if (! $hit) {
            return null; // leave unenriched; surface these in the UI for manual fixing
        }

        return new GeocodeResult(
            lat: (float) $hit['lat'],
            lng: (float) $hit['lon'],
            address: $hit['display_name'] ?? null,
        );
    }

    /**
     * Nominatim's usage policy caps requests at 1/sec. A blocking sleep is
     * enough here since EnrichPlace jobs run one at a time on a single
     * queue worker — no concurrent callers to coordinate across processes.
     */
    private function throttle(): void
    {
        sleep(1);
    }
}
