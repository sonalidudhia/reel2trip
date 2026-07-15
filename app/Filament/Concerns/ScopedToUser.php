<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/** Scopes a resource whose model belongsTo(Trip) to the authenticated user's own trips. */
trait ScopedToUser
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('trip', fn (Builder $query) => $query->where('user_id', auth()->id()));
    }
}
