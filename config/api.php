<?php

return [
    'habilitada' => env('API_HABILITADA', true),
    'rate_limit' => (int) env('API_RATE_LIMIT', 120),
    'per_page_max' => 200,
    'per_page_default' => 50,
    'webhooks' => [
        'timeout' => (int) env('WEBHOOKS_TIMEOUT', 4),
        'max_intentos' => (int) env('WEBHOOKS_MAX_INTENTOS', 6),
        // minutos de espera antes de cada reintento, indexado por (intentos - 1)
        'backoff' => [1, 5, 15, 60, 240, 720],
    ],
    'url_firmada_minutos' => 15,
];
