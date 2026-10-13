<?php

/*
 * Family Affairs technical configuration. FAMILY RULES (amounts, penalties,
 * deadlines) are NOT here — they live in the policy_rules table and need
 * leadership confirmation. This file only holds operational settings.
 */
return [

    'messaging' => [
        // nextsms | fake (fake is refused outside local/testing)
        'driver' => env('FA_MESSAGING_DRIVER', 'fake'),
        'fake_allowed_envs' => ['local', 'testing'],
        'retry_backoff_minutes' => [5, 30, 120],
        'max_attempts' => 4,
        // Broadcasts larger than this need a second leader's approval
        'approval_threshold' => 10,
    ],

    'nextsms' => [
        'base_url' => rtrim(env('NEXTSMS_BASE_URL', 'https://messaging-service.co.tz'), '/'),
        'token' => env('NEXTSMS_TOKEN'),
        'whatsapp_account' => env('NEXTSMS_WHATSAPP_ACCOUNT'),
        'otp_template' => env('NEXTSMS_OTP_TEMPLATE'),
        'test_mode' => (bool) env('NEXTSMS_TEST_MODE', false),
        'webhook_token' => env('NEXTSMS_WEBHOOK_TOKEN'),
        'price_per_message' => (int) env('NEXTSMS_PRICE_PER_MESSAGE', 12),
        'timeout' => 20,
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => (int) env('FA_OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('FA_OTP_MAX_ATTEMPTS', 5),
        'resend_seconds' => (int) env('FA_OTP_RESEND_SECONDS', 60),
        'max_sends_per_hour' => (int) env('FA_OTP_MAX_SENDS_PER_HOUR', 5),
        'max_sends_per_ip_per_hour' => 20,
    ],

    // true only on hosts without a supervised `queue:work` (see DEPLOYMENT.md)
    'queue_from_scheduler' => (bool) env('FA_QUEUE_FROM_SCHEDULER', false),

    'invite_ttl_days' => (int) env('FA_INVITE_TTL_DAYS', 14),

    'uploads' => [
        'receipt_max_kb' => 5120,
        'photo_max_kb' => 12288,
        'document_max_kb' => 10240,
    ],

    'locales' => ['sw', 'en'],
];
