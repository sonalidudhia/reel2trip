<?php

namespace App\Filament\Widgets;

use App\Models\Place;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlacesByCategoryOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return Place::query()
            ->whereHas('reel.trip', fn ($q) => $q->where('user_id', auth()->id()))
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->map(fn ($count, $category) => Stat::make(ucfirst($category), (string) $count))
            ->values()
            ->all();
    }
}
