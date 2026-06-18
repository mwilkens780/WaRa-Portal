<?php

return [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
    ],

    'github' => [
        'token'          => env('GITHUB_TOKEN', ''),
        'repo'           => env('GITHUB_REPO', 'mwilkens780/WaRa-Portal'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET', ''),
    ],
];
