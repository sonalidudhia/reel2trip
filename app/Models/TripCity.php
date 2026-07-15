<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripCity extends Model
{
    protected $guarded = [];

    protected $casts = ['arrival_date' => 'date'];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }
}
