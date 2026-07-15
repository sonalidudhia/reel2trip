<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Process (yt-dlp, ffmpeg) inherits PHP's own PATH, which lacks
        // Homebrew's bin dirs when this process isn't spawned from a login shell.
        $path = getenv('PATH') ?: '';
        foreach (['/opt/homebrew/bin', '/opt/homebrew/sbin', '/usr/local/bin'] as $dir) {
            if (! str_contains($path, $dir)) {
                $path = $dir.':'.$path;
            }
        }
        putenv('PATH='.$path);

        // WAL mode lets the queue worker and web requests hit sqlite
        // concurrently without "database is locked" errors.
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA journal_mode=WAL;');
            DB::statement('PRAGMA busy_timeout=5000;');
        }
    }
}
