<?php

return [
    'enabled' => env('MYINVOIS_ENABLED', false),
    'production_enabled' => env('MYINVOIS_PRODUCTION_ENABLED', false),
    'environment' => env('MYINVOIS_ENVIRONMENT', 'sandbox'),
    'document_version' => env('MYINVOIS_DOCUMENT_VERSION', '1.0'),
    'cancellation_window_hours' => env('MYINVOIS_CANCELLATION_WINDOW_HOURS', 72),

    'environments' => [
        'sandbox' => [
            'api_url' => env('MYINVOIS_SANDBOX_API_URL', 'https://preprod-api.myinvois.hasil.gov.my'),
            'portal_url' => env('MYINVOIS_SANDBOX_PORTAL_URL', 'https://preprod.myinvois.hasil.gov.my'),
            'client_id' => env('MYINVOIS_SANDBOX_CLIENT_ID'),
            'client_secret' => env('MYINVOIS_SANDBOX_CLIENT_SECRET'),
        ],
        'production' => [
            'api_url' => env('MYINVOIS_PRODUCTION_API_URL', 'https://api.myinvois.hasil.gov.my'),
            'portal_url' => env('MYINVOIS_PRODUCTION_PORTAL_URL', 'https://myinvois.hasil.gov.my'),
            'client_id' => env('MYINVOIS_PRODUCTION_CLIENT_ID'),
            'client_secret' => env('MYINVOIS_PRODUCTION_CLIENT_SECRET'),
        ],
    ],

    'supplier' => [
        'tin' => env('MYINVOIS_SUPPLIER_TIN'),
        'registration_type' => env('MYINVOIS_SUPPLIER_REGISTRATION_TYPE', 'BRN'),
        'registration_number' => env('MYINVOIS_SUPPLIER_REGISTRATION_NUMBER'),
        'sst_number' => env('MYINVOIS_SUPPLIER_SST_NUMBER', 'NA'),
        'tourism_tax_number' => env('MYINVOIS_SUPPLIER_TTX_NUMBER', 'NA'),
        'msic_code' => env('MYINVOIS_SUPPLIER_MSIC_CODE'),
        'business_activity' => env('MYINVOIS_SUPPLIER_BUSINESS_ACTIVITY'),
        'name' => env('MYINVOIS_SUPPLIER_NAME'),
        'email' => env('MYINVOIS_SUPPLIER_EMAIL'),
        'phone' => env('MYINVOIS_SUPPLIER_PHONE'),
        'address_line_1' => env('MYINVOIS_SUPPLIER_ADDRESS_LINE_1'),
        'address_line_2' => env('MYINVOIS_SUPPLIER_ADDRESS_LINE_2'),
        'city' => env('MYINVOIS_SUPPLIER_CITY'),
        'state_code' => env('MYINVOIS_SUPPLIER_STATE_CODE'),
        'postcode' => env('MYINVOIS_SUPPLIER_POSTCODE'),
        'country_code' => env('MYINVOIS_SUPPLIER_COUNTRY_CODE', 'MYS'),
    ],

    'http' => [
        'timeout' => env('MYINVOIS_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('MYINVOIS_HTTP_CONNECT_TIMEOUT', 10),
        'verify_tls' => env('MYINVOIS_VERIFY_TLS', true),
    ],

    'cache' => [
        'token_key' => 'myinvois.access_token',
        'token_ttl_buffer_seconds' => 60,
    ],
];
