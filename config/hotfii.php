<?php

return [
    'currency' => 'NGN',
    'timezone' => env('APP_TIMEZONE', 'Africa/Lagos'),
    'commerce' => [
        'platform_fee_bps' => (int) env('HOTFII_PLATFORM_FEE_BPS', 200),
        'standard_minimum_kobo' => (int) env('HOTFII_STANDARD_MINIMUM_KOBO', 250000),
        'micro_sales_limit_kobo' => (int) env('HOTFII_MICRO_SALES_LIMIT_KOBO', 5000000),
        'trial_sales_cap_kobo' => (int) env('HOTFII_TRIAL_SALES_CAP_KOBO', 10000000),
        'trial_days' => (int) env('HOTFII_TRIAL_DAYS', 14),
        'grace_days' => (int) env('HOTFII_GRACE_DAYS', 7),
    ],
    'radius' => [
        'host' => env('HOTFII_RADIUS_HOST', '127.0.0.1'),
        'auth_port' => (int) env('HOTFII_RADIUS_AUTH_PORT', 1812),
        'accounting_port' => (int) env('HOTFII_RADIUS_ACCT_PORT', 1813),
        'coa_port' => (int) env('HOTFII_COA_PORT', 3799),
    ],
    'wireguard' => [
        'server_public_key' => env('HOTFII_WIREGUARD_SERVER_PUBLIC_KEY'),
        'server_address' => env('HOTFII_WIREGUARD_SERVER_ADDRESS', '10.77.0.1'),
        'endpoint' => env('HOTFII_WIREGUARD_ENDPOINT'),
        'port' => (int) env('HOTFII_WIREGUARD_PORT', 51820),
        'allowed_addresses' => env('HOTFII_WIREGUARD_ALLOWED_ADDRESSES', '10.77.0.0/16'),
        'agent_url' => env('HOTFII_WIREGUARD_AGENT_URL', 'http://172.18.0.1:8787'),
        'agent_secret' => env('HOTFII_WIREGUARD_AGENT_SECRET'),
    ],
    // Every vendor the codebase knows about. Adapters, guides and enum cases
    // stay in place for all of them.
    'supported_vendors' => ['generic', 'mikrotik', 'unifi', 'omada', 'ruijie', 'cambium', 'cisco', 'huawei', 'dlink'],

    // The subset an operator may actually pick when adding a device. The rest
    // are hidden rather than removed: their adapters are untested against real
    // hardware, so offering them would promise support that does not exist yet.
    // Add a vendor here once it has been proven on a live device.
    'selectable_vendors' => ['generic', 'mikrotik', 'omada', 'unifi'],
    'internal_plans' => [
        'organization_20' => ['price_kobo' => 500000, 'sites' => 1, 'active_identities' => 20],
        'organization_50' => ['price_kobo' => 750000, 'sites' => 1, 'active_identities' => 50],
        'organization_250' => ['price_kobo' => 2000000, 'sites' => 3, 'active_identities' => 250],
        'institution_1000' => ['price_kobo' => 5000000, 'sites' => 10, 'active_identities' => 1000],
    ],
];