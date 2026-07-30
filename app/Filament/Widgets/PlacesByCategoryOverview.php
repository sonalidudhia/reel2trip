<?php

namespace App\Filament\Widgets;

use App\Models\Place;
use App\Support\PlaceCategories;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** What kind of places the reels are actually yielding. */
class PlacesByCategoryOverview extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $counts = Place::query()
            ->whereHas('reel.trip', fn ($q) => $q->where('user_id', auth()->id()))
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        // Every category, in planning order, so the row doesn't reshuffle as
        // counts change — a zero is information too.
        return collect(array_keys(PlaceCategories::LABELS))
            ->map(fn (string $category) => Stat::make(
                PlaceCategories::label($category),
                (string) ($counts[$category] ?? 0),
            )
                ->descriptionIcon(PlaceCategories::icon($category))
                ->color(($counts[$category] ?? 0) > 0 ? PlaceCategories::color($category) : 'gray'))
            ->all();
    }
}
