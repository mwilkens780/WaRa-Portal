<?php

/**
 * Wie Laravels Standard, mit einem zweiten Broker fuer Einrichtungslinks.
 *
 * Ein Link zum Zuruecksetzen soll kurzlebig sein - eine Stunde. Der Link aus
 * der Willkommensmail muss laenger halten: Mitglieder lesen ihre Mail nicht
 * binnen einer Stunde, und ein abgelaufener Link beim ersten Kontakt mit dem
 * Portal kostet mehr, als er an Sicherheit bringt. Beide Broker nutzen
 * dieselbe Tabelle; ein neuer Link macht den vorherigen ungueltig.
 */
return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => env('AUTH_MODEL', App\Models\User::class),
        ],
    ],

    'passwords' => [
        // Passwort vergessen / zuruecksetzen
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,          // Minuten
            'throttle' => 60,          // Sekunden zwischen zwei Anforderungen
        ],

        // Erstmalige Einrichtung aus der Willkommensmail
        'welcome' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60 * 24 * 14,   // 14 Tage
            'throttle' => 0,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
