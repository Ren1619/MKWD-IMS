<?php

return [
    'enabled' => env('REALTIME_ENABLED', env('BROADCAST_CONNECTION') === 'reverb'),
    'host' => env('VITE_REVERB_HOST', env('REVERB_HOST', 'localhost')),
    'port' => (int) env('VITE_REVERB_PORT', env('REVERB_PORT', 8080)),
    'scheme' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'http')),
    'key' => env('REVERB_APP_KEY'),
];
