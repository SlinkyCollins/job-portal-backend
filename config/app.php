<?php

return [
    'jobs' => [
        'supported_currencies' => ['USD', 'NGN', 'GBP', 'EUR'],
    ],
    'validation' => [
        'valid_countries' => require __DIR__ . '/countries.php',
    ],
    'uploads' => [
        'cv_max_size' => 10 * 1024 * 1024,
        'company_logo_max_size' => 2 * 1024 * 1024,
    ],
];
