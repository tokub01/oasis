<?php

return [
    // Transport connection: pusher in the local deployment, null in tests; ciphertext only.
    'default' => env('BROADCAST_CONNECTION', 'null'),
    'connections' => [
        'pusher' => [
            // Application credentials come only from deployment environment variables.
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                // EU Channels cluster; TLS is mandatory for this transport.
                'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                'useTLS' => true,
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
