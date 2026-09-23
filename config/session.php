<?php

/**
 * Wie Laravels Standard, mit einer Ausnahme: das Sitzungs-Cookie wird
 * automatisch als "secure" markiert, sobald das Portal unter https laeuft.
 *
 * Ohne dieses Kennzeichen schickt der Browser das Cookie auch ueber eine
 * unverschluesselte Verbindung - und wer im selben Netz mitliest, hat damit
 * die Sitzung. In der Entwicklung unter http bleibt es aus, sonst waere kein
 * Login moeglich.
 */
return [

    'driver'          => env('SESSION_DRIVER', 'file'),
    'lifetime'        => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),
    'encrypt'         => env('SESSION_ENCRYPT', false),
    'files'           => storage_path('framework/sessions'),
    'connection'      => env('SESSION_CONNECTION'),
    'table'           => env('SESSION_TABLE', 'sessions'),
    'store'           => env('SESSION_STORE'),
    'lottery'         => [2, 100],

    'cookie' => env(
        'SESSION_COOKIE',
        \Illuminate\Support\Str::slug(env('APP_NAME', 'laravel'), '_') . '_session'
    ),

    'path'   => env('SESSION_PATH', '/'),
    'domain' => env('SESSION_DOMAIN'),

    // Standard richtet sich nach der Portal-Adresse; per .env uebersteuerbar
    'secure' => env('SESSION_SECURE_COOKIE', str_starts_with((string) env('APP_URL'), 'https://')),

    'http_only'   => env('SESSION_HTTP_ONLY', true),
    'same_site'   => env('SESSION_SAME_SITE', 'lax'),
    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];
