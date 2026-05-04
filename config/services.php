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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'casey' => [
        'base_url' => env('CASEY_API_BASE_URL'),
        'username' => env('CASEY_API_USERNAME', 'MAK001'),
        'password' => env('CASEY_API_PASSWORD', '6261622'),
        'auth_endpoint' => env('CASEY_API_AUTH_ENDPOINT', '/casey/auth/sign-in'),
        'premium_batch_endpoint' => env('CASEY_API_PREMIUM_BATCH_ENDPOINT', '/casey/v1/premiums/batch/detailed_info'),
        'premium_batch_id' => env('CASEY_API_PREMIUM_BATCH_ID', 5239),
        'verify_ssl' => env('CASEY_API_VERIFY_SSL', true),
        'token_cache_ttl' => env('CASEY_API_TOKEN_CACHE_TTL', 50),

        // Layer 1 - Reference data sync (CAPS owns the Company / Municipality master data)
        'municipalities_endpoint' => env(
            'CASEY_API_MUNICIPALITIES_ENDPOINT',
            '/v1/admin/organization/municipalities'
        ),
        'companies_endpoint' => env(
            'CASEY_API_COMPANIES_ENDPOINT',
            '/v1/admin/organization/companies'
        ),
        'sync_only_active' => env('CASEY_API_SYNC_ONLY_ACTIVE', true),
        'sync_default_province' => env('CASEY_API_SYNC_DEFAULT_PROVINCE'),

        // Layer 4 - Single sign-on. The Tracker verifies CAPS-issued JWTs using
        // the same HS256 shared secret CAPS itself uses to sign them
        // (`com.casey.supportal.jwt.token.secretkey` in CAPS config).
        'jwt_shared_secret' => env('CASEY_JWT_SHARED_SECRET'),
        'jwt_leeway_seconds' => env('CASEY_JWT_LEEWAY_SECONDS', 30),
        'sso_enabled' => env('CASEY_SSO_ENABLED', false),
        'sso_auto_provision' => env('CASEY_SSO_AUTO_PROVISION', true),
        'sso_default_role' => env('CASEY_SSO_DEFAULT_ROLE', 'user'),
        'sso_redirect_route' => env('CASEY_SSO_REDIRECT_ROUTE', 'dashboard'),

        // Auto-redirect: when an unauthenticated user lands on the Tracker
        // they are bounced to the CAPS handoff URL. The handoff page in CAPS
        // pulls the JWT from local storage and redirects back to the SSO
        // callback. `sso_skip_seconds` is a short cool-down (cookie-based
        // loop guard) used after a failed SSO attempt.
        'sso_auto_redirect' => env('CASEY_SSO_AUTO_REDIRECT', true),
        'sso_handoff_url' => env('CASEY_SSO_HANDOFF_URL'),
        'sso_logout_url' => env('CASEY_SSO_LOGOUT_URL'),
        'sso_skip_seconds' => env('CASEY_SSO_SKIP_SECONDS', 60),

        // Layer 3 — CAPS webhook verification. CAPS signs every webhook
        // payload with HMAC-SHA256 using this shared secret.
        'webhook_secret' => env('CAPS_WEBHOOK_SECRET'),

        // SSO Session microservice — centralized session store shared
        // between CAPS and the Tracker for bidirectional login/logout sync.
        'sso_service_url' => env('CASEY_SSO_SERVICE_URL', 'http://localhost:4000'),
        'sso_api_secret' => env('CASEY_SSO_API_SECRET', 'casey-sso-dev-secret'),
    ],

];
