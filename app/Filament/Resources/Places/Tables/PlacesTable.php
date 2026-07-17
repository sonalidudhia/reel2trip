<?php

namespace App\Filament\Resources\Places\Tables;

use App\Models\TripCity;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
                TextColumn::make('name')->searchable(),
                TextColumn::make('category'),
                TextColumn::make('tripCity.name')->label('City'),
                ToggleColumn::make('must_do'),
                ToggleColumn::make('dismissed'),
            ])
            ->filters([
                SelectFilter::make('trip_city_id')
                    ->label('City')
                    ->options(fn () => TripCity::whereHas('trip', fn ($q) => $q->where('user_id', auth()->id()))->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('category')
                    ->options([
                        'food' => 'Food',
                        'sight' => 'Sight',
                        'viewpoint' => 'Viewpoint',
                        'activity' => 'Activity',
                        'area' => 'Area',
                        'tip' => 'Tip',
                    ]),
                TernaryFilter::make('enriched')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('lat'),
                        false: fn (Builder $query) => $query->whereNull('lat'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignToCity')
                        ->label('Assign to city')
                        ->schema([
                            Select::make('trip_city_id')
                                ->label('City')
                                ->options(fn () => TripCity::whereHas('trip', fn ($q) => $q->where('user_id', auth()->id()))->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update(['trip_city_id' => $data['trip_city_id']])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
