<?php

return [

    /*
    | Locales shipped in the mobile app. pt-BR first for the Brazil test.
    */
    'locales' => ['en', 'pt-BR'],

    /*
    | Feature flags served to the app via GET /api/flags.
    | During the Brazil test window everything is unlocked (free_mode)
    | and ad surfaces are hidden — the code paths exist but stay off.
    */
    'flags' => [
        'free_mode' => env('FEATURE_FREE_MODE', true),
        'min_supported_version' => '0.1.0',
        'locales' => ['en', 'pt-BR'],
        'default_locale' => 'en',
        'features' => [
            'auth_device' => true,
            'auth_social' => false,
            'coaching_packs' => false,
            'cloud_upload' => false,
            'streaming' => false,
            'tournaments' => false,
            'games' => false,
            'payments' => false,
        ],
        'ads' => [
            'enabled' => env('ADS_ENABLED', false),
            'banner_top' => true,
            'banner_bottom' => true,
            'native_feed' => true,
            'stream_overlay' => true,
            'overlay_interval_minutes' => 10,
            'rewarded' => true,
            'interstitial' => false,
            'app_open' => false,
        ],
        'credits' => [
            'rewarded_ad_amount' => 10,
            'daily_bonus_amount' => 5,
        ],
    ],

];
