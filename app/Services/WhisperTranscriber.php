<?php

namespace App\Services;

use App\Models\Reel;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Extracts audio with ffmpeg, transcribes locally with whisper.cpp
 * (`whisper-cli`) — free, no API key, no quota.
 */
class WhisperTranscriber
{
    public function transcribe(Reel $reel): void
    {
        if (! $reel->video_path || ! file_exists($reel->video_path)) {
            return; // nothing to transcribe; caption-only reels are still useful
        }

        $audioPath = preg_replace('/\.\w+$/', '.mp3', $reel->video_path);

        $result = Process::timeout(120)->run([
            'ffmpeg', '-y', '-i', $reel->video_path,
            '-vn', '-ar', '16000', '-ac', '1', '-b:a', '64k',
            $audioPath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('ffmpeg failed: ' . $result->errorOutput());
        }

        $reel->update(['transcript' => $this->transcribeLocal($audioPath)]);

        @unlink($audioPath);
    }

    private function transcribeLocal(string $audioPath): string
    {
        $result = Process::timeout(180)->run([
            'whisper-cli',
            '-m', config('services.whisper.model_path'),
            '-f', $audioPath,
            '-np', '-nt',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('whisper-cli failed: ' . $result->errorOutput());
        }

        return trim($result->output());
    }
}
