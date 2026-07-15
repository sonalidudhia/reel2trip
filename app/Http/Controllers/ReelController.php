<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessReel;
use App\Models\Reel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReelController extends Controller
{
    /**
     * Paste one URL — or a whole bunch, one per line, straight from
     * your Instagram "Saved" collection.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'urls' => ['required', 'string'],
        ]);

        $urls = collect(preg_split('/\s+/', $validated['urls']))
            ->filter(fn ($url) => str_contains($url, 'instagram.com'))
            ->unique();

        foreach ($urls as $url) {
            $reel = Reel::firstOrCreate(
                ['shortcode' => Reel::shortcodeFromUrl($url)],
                ['url' => $url, 'status' => Reel::STATUS_PENDING]
            );

            if ($reel->wasRecentlyCreated || $reel->status === Reel::STATUS_FAILED) {
                ProcessReel::dispatch($reel);
            }
        }

        return back()->with('status', $urls->count() . ' reel(s) queued.');
    }
}
