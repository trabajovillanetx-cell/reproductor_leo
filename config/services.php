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

    /*
    | The Movie Database (TMDB) API v3 — carátulas para VOD/Series sin poster.
    | Clave gratuita: https://www.themoviedb.org/settings/api
    */
    'jellyfin' => [
        'url'     => env('JELLYFIN_URL', ''),
        'api_key' => env('JELLYFIN_API_KEY', ''),
    ],
    'tmdb' => [
        'key' => env('TMDB_API_KEY'),
        'language' => env('TMDB_LANGUAGE', 'es-ES'),
        'image_base' => rtrim(env('TMDB_IMAGE_BASE', 'https://image.tmdb.org/t/p/w500'), '/'),
        'connect_timeout' => (int) env('TMDB_CONNECT_TIMEOUT', 20),
        'timeout' => (int) env('TMDB_HTTP_TIMEOUT', 30),
        'retries' => max(0, min(8, (int) env('TMDB_HTTP_RETRIES', 3))),
        'retry_delay_ms' => max(0, min(10_000, (int) env('TMDB_RETRY_DELAY_MS', 800))),
        'delay_ms_between_requests' => (int) env('TMDB_DELAY_MS', 200),
        'auto_on_import' => filter_var(env('TMDB_AUTO_POSTER_ON_IMPORT', false), FILTER_VALIDATE_BOOL),
    ],

];
