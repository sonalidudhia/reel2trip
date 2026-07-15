<?php

namespace App\Providers;

use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\GooglePlacesGeocoder;
use App\Services\Geocoding\NominatimGeocoder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class GeocodingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Geocoder::class, function () {
            if (config('services.geocoder.driver') === 'google') {
                if (! config('services.google.places_key')) {
                    Log::error('GEOCODER_DRIVER=google but GOOGLE_PLACES_API_KEY is not set; falling back to Nominatim.');

                    return new NominatimGeocoder;
                }

                return new GooglePlacesGeocoder;
            }

            return new NominatimGeocoder;
        });
    }
}
