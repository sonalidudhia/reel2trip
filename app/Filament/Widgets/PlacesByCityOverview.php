<?php

namespace App\Filament\Widgets;

use App\Models\Place;
use App\Models\TripCity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Per-city totals, with how much of each is actually on the list. */
class PlacesByCityOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Counted in one grouped query rather than a withCount alias, so the
        // "on your list" figure stays a real column we can read by city id.
        $selected = Place::query()
            ->whereHas('reel.trip', fn ($q) => $q->where('user_id', auth()->id()))
            ->where('selected', true)
            ->selectRaw('trip_city_id, count(*) as count')
            ->groupBy('trip_city_id')
            ->pluck('count', 'trip_city_id');

        return TripCity::query()
            ->whereHas('trip', fn ($q) => $q->where('user_id', auth()->id()))
            ->withCount('places')
            ->orderBy('position')
            ->get()
            ->map(function (TripCity $city) use ($selected) {
                $onList = (int) ($selected[$city->id] ?? 0);

                return Stat::make($city->name, (string) $city->places_count)
                    ->description($onList.' on your list · '.$city->days.' '.str('day')->plural($city->days))
                    ->descriptionIcon('heroicon-m-map-pin')
                    ->color($onList > 0 ? 'primary' : 'gray');
            })
            ->all();
    }
}
