<?php

namespace App\Filament\Resources\Places;

use App\Filament\Resources\Places\Pages\EditPlace;
use App\Filament\Resources\Places\Pages\ListPlaces;
use App\Filament\Resources\Places\Pages\ViewPlace;
use App\Filament\Resources\Places\Schemas\PlaceForm;
use App\Filament\Resources\Places\Schemas\PlaceInfolist;
use App\Filament\Resources\Places\Tables\PlacesTable;
use App\Models\Place;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlaceResource extends Resource
{
    protected static ?string $model = Place::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'All Places';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('reel.trip', fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    public static function form(Schema $schema): Schema
    {
        return PlaceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlaceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlacesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaces::route('/'),
            'view' => ViewPlace::route('/{record}'),
            'edit' => EditPlace::route('/{record}/edit'),
        ];
    }
}
