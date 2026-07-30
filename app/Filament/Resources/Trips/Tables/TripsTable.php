<?php

namespace App\Filament\Resources\Trips\Tables;

use App\Models\Trip;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    // The route reads better as a line under the trip name than as
                    // a separate column you have to cross-reference.
                    ->description(fn (Trip $record) => $record->tripCities->pluck('name')->implode(' → ') ?: null),
                TextColumn::make('start_date')
                    ->date('j M Y')
                    ->placeholder('No date set')
                    ->sortable(),
                TextColumn::make('trip_cities_count')
                    ->counts('tripCities')
                    ->label('Cities')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('reels_count')
                    ->counts('reels')
                    ->label('Reels')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->emptyStateIcon('heroicon-o-globe-europe-africa')
            ->emptyStateHeading('No trips yet')
            ->emptyStateDescription('A trip holds your cities and the reels you collect for them.')
            ->emptyStateActions([
                CreateAction::make()->label('Create a trip'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
