<?php

namespace App\Filament\Widgets;

use App\Models\Place;
use App\Models\Reel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline numbers: what came in, what came out of it, what's still moving.
 * Deliberately four stats rather than one per status — the useful read is the
 * funnel, not "transcribing" vs "extracting" as separate tiles.
 */
class ReelStatusOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $counts = Reel::query()
            ->whereHas('trip', fn ($q) => $q->where('user_id', auth()->id()))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $inProgress = $counts->only([
            Reel::STATUS_PENDING,
            Reel::STATUS_DOWNLOADING,
            Reel::STATUS_TRANSCRIBING,
            Reel::STATUS_EXTRACTING,
        ])->sum();

        $failed = (int) ($counts[Reel::STATUS_FAILED] ?? 0);

        $places = Place::query()->whereHas('reel.trip', fn ($q) => $q->where('user_id', auth()->id()));

        return [
            Stat::make('Reels processed', (string) ($counts[Reel::STATUS_DONE] ?? 0))
                ->description($counts->sum().' added in total')
                ->descriptionIcon('heroicon-m-film')
                ->color('success'),

            Stat::make('Places found', (string) $places->clone()->count())
                ->description('Extracted from your reels')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary'),

            Stat::make('On your list', (string) $places->clone()->where('selected', true)->count())
                ->description($places->clone()->where('must_do', true)->count().' marked must-do')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('Still working', (string) ($inProgress + $failed))
                ->description("{$inProgress} in progress, {$failed} failed")
                ->descriptionIcon($failed > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-arrow-path')
                ->color($failed > 0 ? 'danger' : 'gray'),
        ];
    }
}
