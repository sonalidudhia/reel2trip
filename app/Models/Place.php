<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Place extends Model
{
    protected $guarded = [];

    protected $casts = [
        'opening_hours' => 'array',
        'must_do' => 'boolean',
        'dismissed' => 'boolean',
        'selected' => 'boolean',
    ];

    public const CATEGORY_TIP = 'tip';

    private const GOOGLE_MAPS_SEARCH = 'https://www.google.com/maps/search/';

    protected static function booted(): void
    {
        static::saving(function (Place $place) {
            if (! $place->selected) {
                $place->must_do = false;
            }
        });
    }

    /** @return BelongsTo<Reel, $this> */
    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class);
    }

    /** @return BelongsTo<TripCity, $this> */
    public function tripCity(): BelongsTo
    {
        return $this->belongsTo(TripCity::class);
    }

    public function isEnriched(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function isTip(): bool
    {
        return $this->category === self::CATEGORY_TIP;
    }

    public function hasMapsLocation(): bool
    {
        return $this->isEnriched() || $this->google_place_id !== null;
    }

    public function mapsUrl(?string $cityName = null): ?string
    {
        if ($this->isTip() || ! $this->hasMapsLocation()) {
            return null;
        }

        $parameters = $this->google_place_id === null
            ? ['api' => 1, 'query' => $this->searchableName($cityName)]
            : ['api' => 1, 'query' => $this->name, 'query_place_id' => $this->google_place_id];

        return self::GOOGLE_MAPS_SEARCH.'?'.http_build_query($parameters);
    }

    public function searchableName(?string $cityName = null): string
    {
        return implode(', ', array_filter([$this->name, $cityName]));
    }

    public function priceLabel(): ?string
    {
        return $this->price_level === null
            ? null
            : str_repeat('€', max(1, (int) $this->price_level));
    }
}
