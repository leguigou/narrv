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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'max_input_characters' => env('DEEPSEEK_MAX_INPUT_CHARACTERS', 45000),
    ],

    'youtube' => [
        'yt_dlp_path' => env('YT_DLP_PATH', 'yt-dlp'),
        'cookies_path' => env('YOUTUBE_COOKIES_PATH', storage_path('app/youtube-cookies.txt')),
        'sleep_requests' => env('YT_DLP_SLEEP_REQUESTS', 1),
        'retries' => env('YT_DLP_RETRIES', 5),
        'retry_sleep' => env('YT_DLP_RETRY_SLEEP', 'http:exp=1:20'),
        'js_runtimes' => env('YT_DLP_JS_RUNTIMES', 'deno'),
    ],

    'admin' => [
        'password' => env('ADMIN_PASSWORD'),
    ],

];
