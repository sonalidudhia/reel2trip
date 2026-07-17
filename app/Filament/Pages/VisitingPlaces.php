<?php

namespace App\Filament\Pages;

use App\Models\Place;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Everywhere you're actually visiting, grouped by city, across every trip. */
class VisitingPlaces extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.visiting-places';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::MapPin;

    protected static ?string $navigationLabel = 'Visiting Places';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Place::query()
                    ->where('selected', true)
                    ->whereHas('reel', fn (Builder $q) => $q->whereHas('trip', fn (Builder $q2) => $q2->where('user_id', auth()->id())))
            )
            ->groups([
                Group::make('trip_city_id')
                    ->label('City')
                    ->getTitleFromRecordUsing(fn (Place $record) => $record->tripCity
                        ? "{$record->tripCity->name}, {$record->tripCity->country}"
                        : 'Unassigned')
                    ->collapsible(),
            ])
            ->defaultGroup('trip_city_id')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('category'),
                IconColumn::make('must_do')
                    ->label('Must do')
                    ->boolean(),
                TextColumn::make('rating'),
            ])
            ->filters([
                TernaryFilter::make('must_do'),
            ]);
    }
}
