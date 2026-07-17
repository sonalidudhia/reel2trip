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

    /** must_do only makes sense for a place you're actually visiting. */
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

    /** "€" to "€€€€" from Google's 0-4 price level. */
    public function priceLabel(): ?string
    {
        return $this->price_level === null
            ? null
            : str_repeat('€', max(1, (int) $this->price_level));
    }
}
