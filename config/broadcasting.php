<?php

return [
    // Transport connection: reverb in the local and production deployment, null in tests; ciphertext only.
    'default' => env('BROADCAST_CONNECTION', 'null'),
    'connections' => [
        'reverb' => [
            // Application credentials come only from deployment environment variables; server runs self-hosted via Reverb.
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                // In Sail, PHP reaches the reverb service container while browsers connect via REVERB_HOST / REVERB_PORT.
                'host' => env('REVERB_SERVER_CONNECT_HOST', env('REVERB_HOST', '127.0.0.1')),
                'port' => env('REVERB_SERVER_CONNECT_PORT', env('REVERB_PORT', 8080)),
                'scheme' => env('REVERB_SERVER_CONNECT_SCHEME', env('REVERB_SCHEME', 'http')),
                'useTLS' => env('REVERB_SERVER_CONNECT_SCHEME', env('REVERB_SCHEME', 'http')) === 'https',
            ],
            // Bound synchronous publish latency; durable ciphertext remains retrievable on failure.
            'client_options' => ['timeout' => 5, 'connect_timeout' => 3],
        ],
        // Tests dispatch events without any external network traffic.
        'null' => ['driver' => 'null'],
        // Local troubleshooting logs ciphertext only; never select this in production.
        'log' => ['driver' => 'log'],
    ],
];
