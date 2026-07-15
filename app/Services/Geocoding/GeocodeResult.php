<?php

namespace App\Services\Geocoding;

final readonly class GeocodeResult
{
    /** @param  array<int, string>|null  $openingHours */
    public function __construct(
        public float $lat,
        public float $lng,
        public ?string $address = null,
        public ?float $rating = null,
        public ?int $priceLevel = null,
        public ?array $openingHours = null,
        public ?string $googlePlaceId = null,
    ) {}
}
