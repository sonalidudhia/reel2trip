<?php

namespace App\Filament\Resources\Places\Pages;

use App\Filament\Resources\Places\PlaceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPlaces extends ListRecords
{
    protected static string $resource = PlaceResource::class;

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('dismissed', false)),
            'visiting' => Tab::make('Visiting')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('dismissed', false)->where('selected', true)),
            'dismissed' => Tab::make('Dismissed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('dismissed', true)),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'active';
    }
}
