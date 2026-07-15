<?php

namespace App\Filament\Resources\Reels\Schemas;

use App\Models\Reel;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Extracted text')
                    ->schema([
                        TextEntry::make('combined')
                            ->label('')
                            ->state(fn (Reel $record) => $record->combinedText())
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
