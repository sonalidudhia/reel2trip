<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $guarded = [];

    protected $casts = ['start_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tripCities(): HasMany
    {
        return $this->hasMany(TripCity::class)->orderBy('position');
    }

    public function reels(): HasMany
    {
        return $this->hasMany(Reel::class);
    }
}
