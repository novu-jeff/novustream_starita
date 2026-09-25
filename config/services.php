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

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Past-due SOA QR (align with NovuPay DUE_DATE_QR_PENALTY):
    | true  = allow online payment after due date using amount_after_due / penalty
    | false = void QR and show payments.qr-voided
    */
    'due_date_qr_penalty' => env('DUE_DATE_QR_PENALTY', true),

    /*
    | NovuPay hosted checkout (Sta-Rita Water District / NovuBiz).
    | Keep API key and client secret server-side only. Never send client_secret
    | on checkout requests. QR Ph biller name is set by NovuPay, not staging.
    */
    'novupay' => [
        'api_url' => rtrim((string) env('NOVUPAY_API_URL', 'https://api.novu-pay.com'), '/'),
        'checkout_base' => rtrim((string) env('NOVUPAY_CHECKOUT_BASE', 'https://novu-pay.com'), '/'),
        'api_key' => env('NOVUPAY_API_KEY'),
        'client_id' => env('NOVUPAY_CLIENT_ID'),
        'client_secret' => env('NOVUPAY_CLIENT_SECRET'),
        'public_url' => env('NOVUPAY_PUBLIC_URL'),
        // LAN IP for CURLOPT_RESOLVE when hairpin to the public VIP fails. Not DNS.
        'api_resolve' => env('NOVUPAY_API_RESOLVE'),
        'min_amount' => (float) env('NOVUPAY_MIN_AMOUNT', 100),
        'enabled_channels' => ['qrph'],
        'description' => env('NOVUPAY_DESCRIPTION', 'Sta-Rita Water District bill'),
    ],

];
