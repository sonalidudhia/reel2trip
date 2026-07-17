<?php

namespace App\Filament\Resources\Reels\Pages;

use App\Filament\Resources\Reels\ReelResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewReel extends ViewRecord
{
    protected static string $resource = ReelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }
}
