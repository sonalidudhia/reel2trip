<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reel extends Model
{
    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_DOWNLOADING = 'downloading';

    public const STATUS_TRANSCRIBING = 'transcribing';

    public const STATUS_EXTRACTING = 'extracting';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    /** All text we managed to pull out of the reel, for the extractor. */
    public function combinedText(): string
    {
        return collect([
            'CAPTION:' => $this->caption,
            'TRANSCRIPT:' => $this->transcript,
            'ON-SCREEN TEXT:' => $this->ocr_text,
        ])
            ->filter()
            ->map(fn ($text, $label) => $label."\n".trim($text))
            ->implode("\n\n");
    }

    public static function shortcodeFromUrl(string $url): ?string
    {
        preg_match('~instagram\.com/(?:reel|reels|p)/([A-Za-z0-9_-]+)~', $url, $m);

        return $m[1] ?? null;
    }
}
