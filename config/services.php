<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'), // unused now that extraction runs locally via Ollama
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model'    => env('OLLAMA_MODEL', 'llama3.2:3b'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'), // unused now that transcription runs locally via whisper-cli
    ],

    'whisper' => [
        'bin'        => env('WHISPER_BIN', 'whisper-cli'),
        'model_path' => env('WHISPER_MODEL', env('WHISPER_MODEL_PATH', storage_path('app/whisper-models/ggml-base.en.bin'))),
    ],

    'google' => [
        'places_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    'geocoder' => [
        'driver' => env('GEOCODER_DRIVER', 'nominatim'),
    ],

    'instagram' => [
        // Optional: path to a Netscape-format cookies file exported from your
        // browser. Helps yt-dlp avoid IG login walls / rate limits.
        'cookies_file' => env('INSTAGRAM_COOKIES_FILE'),
    ],

];
