<?php

namespace App\Exports;

use App\Models\Place;
use App\Models\TripCity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ExportablePlaces
{
    public function __construct(
        private readonly int $userId,
        private readonly ?int $tripCityId = null,
        private readonly ?string $category = null,
    ) {}

    /** @return Collection<int, Place> */
    public function get(): Collection
    {
        return Place::query()
            ->where('selected', true)
            ->where('dismissed', false)
            ->where('category', '!=', Place::CATEGORY_TIP)
            ->whereHas('reel.trip', fn (Builder $query) => $query->where('user_id', $this->userId))
            ->when($this->tripCityId !== null, fn (Builder $query) => $query->where('trip_city_id', $this->tripCityId))
            ->when($this->category !== null, fn (Builder $query) => $query->where('category', $this->category))
            ->with(['tripCity', 'reel:id,url'])
            ->orderBy('trip_city_id')
            ->orderByDesc('must_do')
            ->orderBy('name')
            ->get();
    }

    public function city(): ?TripCity
    {
        if ($this->tripCityId === null) {
            return null;
        }

        return TripCity::query()
            ->whereKey($this->tripCityId)
            ->whereHas('trip', fn (Builder $query) => $query->where('user_id', $this->userId))
            ->first();
    }

    public function filename(string $extension): string
    {
        $city = $this->city();

        $parts = array_filter([
            'reel2trip',
            $city === null ? 'all' : str($city->name)->slug()->value(),
            $this->category,
            now()->format('Y-m-d'),
        ]);

        return implode('-', $parts).'.'.$extension;
    }
}
