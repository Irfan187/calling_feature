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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'telnyx' => [
        'public_key' => env('TELNYX_PUBLIC_KEY'),
        'api_key' => env('TELNYX_API_KEY'),
        'api_v1_key' => env('TELNYX_API_KEY_V1'),
        'api_v1_user' => env('TELNYX_API_USER_V1'),
        'brand_price' => 4,
        'brand_vetting' => 40,
        'campaign_registration_tmobile' => 50,
        'campaign_creation_fee' => 10,
        'sms_mms_activation_charge' => 0.18,
        'call_forwarding_charge' => 0,
        'phone_number_price' => 1
    ],

];
