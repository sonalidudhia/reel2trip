<?php

namespace App\Filament\Widgets;

use App\Models\Reel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReelStatusOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $counts = Reel::query()
            ->whereHas('trip', fn ($q) => $q->where('user_id', auth()->id()))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return collect([
            Reel::STATUS_PENDING,
            Reel::STATUS_DOWNLOADING,
            Reel::STATUS_TRANSCRIBING,
            Reel::STATUS_EXTRACTING,
            Reel::STATUS_DONE,
            Reel::STATUS_FAILED,
        ])
            ->map(fn (string $status) => Stat::make(ucfirst($status), (string) ($counts[$status] ?? 0)))
            ->all();
    }
}
