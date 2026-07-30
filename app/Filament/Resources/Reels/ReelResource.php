<?php

namespace App\Filament\Resources\Reels;

use App\Filament\Concerns\ScopedToUser;
use App\Filament\Resources\Reels\Pages\ListReels;
use App\Filament\Resources\Reels\Pages\ViewReel;
use App\Filament\Resources\Reels\RelationManagers\PlacesRelationManager;
use App\Filament\Resources\Reels\Schemas\ReelInfolist;
use App\Filament\Resources\Reels\Tables\ReelsTable;
use App\Models\Reel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReelResource extends Resource
{
    use ScopedToUser;

    protected static ?string $model = Reel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?int $navigationSort = 4;

    public static function infolist(Schema $schema): Schema
    {
        return ReelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PlacesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReels::route('/'),
            'view' => ViewReel::route('/{record}'),
        ];
    }
}
