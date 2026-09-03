<?php

namespace App\Filament\Resources\Places\Tables;

use App\Models\Place;
use App\Models\TripCity;
use App\Support\PlaceCategories;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PlacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    // The one-line "what it is" belongs with the name, not in its
                    // own column — it's prose, and prose in a cell wrecks the grid.
                    ->description(fn (Place $record) => $record->description)
                    ->wrap(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => PlaceCategories::label($state))
                    ->color(fn (?string $state) => PlaceCategories::color($state))
                    ->icon(fn (?string $state) => PlaceCategories::icon($state))
                    ->sortable(),
                TextColumn::make('tripCity.name')
                    ->label('City')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Unassigned')
                    ->sortable(),
                TextColumn::make('reel.shortcode')
                    ->label('From reel')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconPosition(IconPosition::After)
                    ->color('primary')
                    ->url(fn (Place $record) => $record->reel?->url)
                    ->openUrlInNewTab()
                    ->tooltip('Open this place\'s reel on Instagram')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('rating')
                    ->label('Rating')
                    ->icon('heroicon-m-star')
                    ->iconColor('warning')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('price_level')
                    ->label('Price')
                    ->formatStateUsing(fn (Place $record) => $record->priceLabel())
                    ->placeholder('—')
                    ->toggleable(),
                // The table leads with the newest extraction, so show when that was
                // — otherwise the ordering is invisible.
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->tooltip(fn (Place $record) => $record->created_at?->toDayDateTimeString())
                    ->sortable(),
                ToggleColumn::make('selected')->label('Visiting'),
                ToggleColumn::make('must_do')
                    ->label('Must do')
                    ->disabled(fn ($record) => ! $record->selected),
                ToggleColumn::make('dismissed')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('trip_city_id')
                    ->label('City')
                    ->options(fn () => TripCity::whereHas('trip', fn ($q) => $q->where('user_id', auth()->id()))->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('category')
                    ->options(PlaceCategories::options()),
                SelectFilter::make('reel')
                    ->label('Reel')
                    ->relationship('reel', 'shortcode', fn ($query) => $query->whereHas('trip', fn ($q) => $q->where('user_id', auth()->id())))
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('selected')->label('Visiting'),
                TernaryFilter::make('enriched')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('lat'),
                        false: fn (Builder $query) => $query->whereNull('lat'),
                    ),
            ])
            ->emptyStateIcon('heroicon-o-map-pin')
            ->emptyStateHeading('No places yet')
            ->emptyStateDescription('Add some Instagram reels and the places mentioned in them will land here.')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignToCity')
                        ->label('Assign to city')
                        ->icon('heroicon-m-map-pin')
                        ->schema([
                            Select::make('trip_city_id')
                                ->label('City')
                                ->options(fn () => TripCity::whereHas('trip', fn ($q) => $q->where('user_id', auth()->id()))->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['trip_city_id' => $data['trip_city_id']])),
                    BulkAction::make('markVisiting')
                        ->label('Mark as visiting')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['selected' => true])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
