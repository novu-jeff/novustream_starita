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

    'novupay' => [
        'api_key' => env('NOVUPAY_API_KEY'),
        'client_id' => env('NOVUPAY_CLIENT_ID'),
        'client_secret' => env('NOVUPAY_CLIENT_SECRET'),
        'api_base' => rtrim((string) env('NOVUPAY_API_BASE', 'https://api.novu-pay.com'), '/'),
        'api_resolve_ip' => env('NOVUPAY_API_RESOLVE_IP'),
        'hosted_base' => rtrim((string) env('NOVUPAY_HOSTED_BASE', 'https://novu-pay.com'), '/'),
        'notification_url' => env('NOVUPAY_NOTIFICATION_URL', 'https://portal.staritawaterdistrictpamp.gov.ph/webhooks/novupay'),
        'redirect_url' => env('NOVUPAY_REDIRECT_URL', env('APP_URL') . '/payments/novupay/return'),
        'min_amount' => 50.00,
    ],

];
