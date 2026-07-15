<?php

namespace App\Services;

use App\Models\Reel;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Downloads a reel's video + caption using yt-dlp.
 *
 * Requirements: `yt-dlp` on PATH. For private/rate-limited access, export your
 * browser cookies to a file and set INSTAGRAM_COOKIES_FILE in .env.
 */
class InstagramDownloader
{
    public function download(Reel $reel): void
    {
        $shortcode = $reel->shortcode ?? Reel::shortcodeFromUrl($reel->url);
        $dir       = Storage::path('reels');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $outputTemplate = $dir . '/' . $shortcode . '.%(ext)s';

        $args = [
            'yt-dlp',
            '--no-playlist',
            '--write-info-json',          // caption + metadata land in a .info.json
            '-o', $outputTemplate,
            '--sleep-requests', '2',      // be gentle; IG rate-limits aggressively
        ];

        if ($cookies = config('services.instagram.cookies_file')) {
            array_push($args, '--cookies', $cookies);
        }

        $args[] = $reel->url;

        $result = Process::timeout(180)->run($args);

        if (! $result->successful()) {
            throw new RuntimeException('yt-dlp failed: ' . $result->errorOutput());
        }

        // Find what we got
        $video = collect(glob($dir . '/' . $shortcode . '.*'))
            ->first(fn ($f) => preg_match('/\.(mp4|mkv|webm)$/', $f));

        $infoJson = $dir . '/' . $shortcode . '.info.json';
        $caption  = null;

        if (file_exists($infoJson)) {
            $meta    = json_decode(file_get_contents($infoJson), true);
            $caption = $meta['description'] ?? null;
        }

        $reel->update([
            'shortcode'  => $shortcode,
            'video_path' => $video,
            'caption'    => $caption,
        ]);
    }
}
