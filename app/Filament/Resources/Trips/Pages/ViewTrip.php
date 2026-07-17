<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Pages\MyPlaces;
use App\Filament\Resources\Trips\TripResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('index')),
            Action::make('myPlaces')
                ->label('My Places')
                ->icon(Heroicon::MapPin)
                ->url(fn () => MyPlaces::getUrl(['trip' => $this->record])),
            EditAction::make(),
        ];
    }
}
