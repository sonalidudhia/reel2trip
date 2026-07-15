<?php

namespace App\Filament\Resources\Places\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                Select::make('category')
                    ->options([
                        'food' => 'Food',
                        'sight' => 'Sight',
                        'viewpoint' => 'Viewpoint',
                        'activity' => 'Activity',
                        'area' => 'Area',
                        'tip' => 'Tip',
                    ])
                    ->required(),
                Textarea::make('description')->columnSpanFull(),
                Textarea::make('tip')->columnSpanFull(),
            ]);
    }
}
