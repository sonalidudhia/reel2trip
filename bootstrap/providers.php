<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\GeocodingServiceProvider;

return [
    AppServiceProvider::class,
    GeocodingServiceProvider::class,
    AdminPanelProvider::class,
];
