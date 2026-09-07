<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
     * bKash's practice system. Only ever the public sandbox details, so a
     * shop on the dev server can be filled in with something that works.
     * Empty on a live server, and the seeding command refuses to run.
     */
    'bkash_sandbox' => [
        'app_key' => env('BKASH_SANDBOX_APP_KEY'),
        'app_secret' => env('BKASH_SANDBOX_APP_SECRET'),
        'username' => env('BKASH_SANDBOX_USERNAME'),
        'password' => env('BKASH_SANDBOX_PASSWORD'),
    ],

    /*
     * Only needed if staff give a shop the Google map. Without it, Google
     * cannot be handed out and the free map is used instead.
     */
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
    ],

];
