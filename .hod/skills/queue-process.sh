#!/bin/bash
set -e
REEL_ID=${1:?'Reel ID required'}
WATCH=${2:-''}

echo "🎬 Reel2Trip Queue Processing"
echo "=============================="
echo "Reel ID: $REEL_ID"

php artisan tinker --execute "
    \$reel = App\Models\Reel::find($REEL_ID);
    if (!\$reel) throw new Exception('Reel not found');
    echo 'Status: ' . \$reel->status . PHP_EOL;
"

echo "📤 Dispatching ProcessReel job..."
php artisan tinker --execute "
    \$reel = App\Models\Reel::find($REEL_ID);
    App\Jobs\ProcessReel::dispatch(\$reel);
    echo 'Dispatched!' . PHP_EOL;
"

[ "$WATCH" == "--watch" ] && echo "💡 Run queue:work to process"
