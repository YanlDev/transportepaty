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
    | Tracksolid / JIMI Open API (GPS de la flota).
    | base_url según tu región: open.10000track.com (global), hk-open / eu-open / us-open .tracksolidpro.com
    | account = tu cuenta Tracksolid; password = en texto plano (el cliente envía md5 en minúsculas).
    */
    'tracksolid' => [
        'base_url' => env('TRACKSOLID_BASE_URL', 'https://us-open.tracksolidpro.com/route/rest'),
        'app_key' => env('TRACKSOLID_APP_KEY'),
        'app_secret' => env('TRACKSOLID_APP_SECRET'),
        'account' => env('TRACKSOLID_ACCOUNT'),
        // Usa el md5 directamente (recomendado). Si solo tienes la contraseña en
        // texto plano, pon TRACKSOLID_PASSWORD y el cliente calculará el md5.
        'password_md5' => env('TRACKSOLID_PASSWORD_MD5'),
        'password' => env('TRACKSOLID_PASSWORD'),
    ],

];
