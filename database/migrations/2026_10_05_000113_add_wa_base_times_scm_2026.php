<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * World Aquatics Points – Basiszeiten Kurzbahn (25 m) 2026,
 * gültig 01.09.2026 – 31.08.2027.
 * Quelle: Points-Base-times-SCM-and-LCM-2026_09.2026.pdf (worldaquatics.com, 05.09.2026)
 *
 * Gegenüber Kurzbahn 2025 geändert (neue Weltrekorde):
 *   Männer 100/200 Rücken, 200 Brust, 100 Schmetterling;
 *   Frauen 100/200/800 Freistil, 200 Rücken, 50 Schmetterling.
 */
return new class extends Migration
{
    private const TIMES = [
        // Strecke => [Männer, Frauen] in Sekunden
        'F' => [50 => [19.90, 22.83], 100 => [44.84, 49.93], 200 => [98.61, 109.36], 400 => [212.25, 230.25], 800 => [440.46, 474.00], 1500 => [846.88, 908.24]],
        'R' => [50 => [22.11, 25.23], 100 => [48.16, 54.02], 200 => [105.12, 117.33]],
        'B' => [50 => [24.95, 28.37], 100 => [55.28, 62.36], 200 => [119.52, 132.50]],
        'S' => [50 => [21.32, 23.72], 100 => [47.68, 52.71], 200 => [106.85, 119.32]],
        'L' => [100 => [49.28, 55.11], 200 => [108.88, 121.63], 400 => [234.81, 255.48]],
    ];

    public function up(): void
    {
        $rows = [];
        foreach (self::TIMES as $discipline => $distances) {
            foreach ($distances as $distance => [$men, $women]) {
                foreach (['M' => $men, 'F' => $women] as $gender => $sec) {
                    $rows[] = [
                        'year' => 2026, 'pool_length' => 25, 'gender' => $gender,
                        'discipline' => $discipline, 'distance_m' => $distance,
                        'base_time_ms' => (int) round($sec * 1000),
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
        }

        // Von Hand schon eingetragene Werte bleiben unberührt
        DB::table('wa_scoring_tables')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::table('wa_scoring_tables')->where('year', 2026)->where('pool_length', 25)->delete();
    }
};
