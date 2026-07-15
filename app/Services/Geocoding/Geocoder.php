<?php

namespace App\Services\Geocoding;

use App\Models\Place;

interface Geocoder
{
    public function geocode(Place $place): ?GeocodeResult;
}
