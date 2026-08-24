#!/bin/bash
php artisan tinker --execute "
    \$places = App\Models\Place::whereNull('lat')->limit(10)->get();
    foreach (\$places as \$place) {
        App\Jobs\EnrichPlace::dispatch(\$place);
    }
    echo 'Dispatched enrichment jobs' . PHP_EOL;
"
