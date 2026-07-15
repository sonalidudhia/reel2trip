<?php

namespace App\Filament\Widgets;

use App\Models\TripCity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlacesByCityOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return TripCity::query()
            ->whereHas('trip', fn ($q) => $q->where('user_id', auth()->id()))
            ->withCount('places')
            ->get()
            ->map(fn (TripCity $city) => Stat::make($city->name, (string) $city->places_count))
            ->all();
    }
}
