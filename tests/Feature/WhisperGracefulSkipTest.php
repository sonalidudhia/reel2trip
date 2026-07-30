<?php

use App\Models\Reel;
use App\Services\WhisperTranscriber;
use Illuminate\Support\Facades\Process;

function makeReelWithVideo(): Reel
{
    $path = tempnam(sys_get_temp_dir(), 'reel2trip_test_video').'.mp4';
    file_put_contents($path, 'not a real video, just needs to exist');

    return Reel::create([
        'url' => 'https://instagram.com/reel/'.uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_DOWNLOADING,
        'video_path' => $path,
    ]);
}

test('skips gracefully without throwing when the whisper binary is missing', function () {
    config(['services.whisper.bin' => 'definitely-not-a-real-binary-xyz']);

    $reel = makeReelWithVideo();

    (new WhisperTranscriber)->transcribe($reel);

    expect($reel->fresh()->transcript)->toBeNull();

    @unlink($reel->video_path);
});

test('returns without transcribing when the reel has no video', function () {
    $reel = Reel::create([
        'url' => 'https://instagram.com/reel/'.uniqid(),
        'shortcode' => uniqid(),
        'status' => Reel::STATUS_PENDING,
        'video_path' => null,
    ]);

    (new WhisperTranscriber)->transcribe($reel);

    expect($reel->fresh()->transcript)->toBeNull();
});

test('ffmpeg and whisper are capped to the configured thread count', function () {
    config(['services.whisper.threads' => 3]);
    Process::fake([
        'which*' => Process::result(output: '/usr/bin/yes'),
        '*' => Process::result(output: 'transcribed text'),
    ]);
    $reel = makeReelWithVideo();

    (new WhisperTranscriber)->transcribe($reel);

    $ran = fn (string $bin, string $flag) => Process::assertRan(function ($process) use ($bin, $flag) {
        $command = implode(' ', (array) $process->command);

        return str_contains($command, $bin) && str_contains($command, $flag);
    });

    $ran('ffmpeg', '-threads 3');
    $ran('whisper-cli', '-t 3');
});
