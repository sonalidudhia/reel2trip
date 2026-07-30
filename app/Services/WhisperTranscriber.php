<?php

namespace App\Services;

use App\Models\Reel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Extracts audio with ffmpeg, transcribes locally with whisper.cpp
 * (`whisper-cli`) — free, no API key, no quota.
 *
 * A missing ffmpeg/whisper-cli binary is a graceful skip (caption-only
 * extraction still works); a binary that's present but fails on the
 * actual audio (corrupt video, bad model file) still throws, since that's
 * a real, retryable failure, not a "feature not installed" case.
 */
class WhisperTranscriber
{
    public function transcribe(Reel $reel): void
    {
        if (! $reel->video_path || ! file_exists($reel->video_path)) {
            return; // nothing to transcribe; caption-only reels are still useful
        }

        if (! $this->binaryExists('ffmpeg') || ! $this->binaryExists(config('services.whisper.bin'))) {
            Log::notice('WhisperTranscriber: ffmpeg or whisper-cli not found on PATH, skipping transcription', [
                'reel_id' => $reel->id,
            ]);

            return;
        }

        $audioPath = preg_replace('/\.\w+$/', '.mp3', $reel->video_path);

        $result = Process::timeout(120)->run([
            'ffmpeg', '-y', '-threads', (string) config('services.whisper.threads'),
            '-i', $reel->video_path,
            '-vn', '-ar', '16000', '-ac', '1', '-b:a', '64k',
            $audioPath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('ffmpeg failed: '.$result->errorOutput());
        }

        $reel->update(['transcript' => $this->transcribeLocal($audioPath)]);

        @unlink($audioPath);
    }

    private function binaryExists(string $bin): bool
    {
        return Process::run(['which', $bin])->successful();
    }

    private function transcribeLocal(string $audioPath): string
    {
        $result = Process::timeout(180)->run([
            config('services.whisper.bin'),
            '-m', config('services.whisper.model_path'),
            '-f', $audioPath,
            '-t', (string) config('services.whisper.threads'),
            '-np', '-nt',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('whisper-cli failed: '.$result->errorOutput());
        }

        return trim($result->output());
    }
}
