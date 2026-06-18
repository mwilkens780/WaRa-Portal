<?php

namespace Database\Seeders;

use App\Models\WaScoringTable;
use Illuminate\Database\Seeder;

/**
 * World Aquatics Points – Base Times
 * Source: https://www.worldaquatics.com/swimming/points
 * PDF: Points-Base-times-SCM-and-LCM-2026_01.2026.pdf
 *
 * SCM 2025: validity 01.09.2025 – 31.08.2026
 * LCM 2026: validity 01.01.2026 – 31.12.2026
 *
 * Discipline codes: F=Freistil, R=Rücken, B=Brust, S=Schmetterling, L=Lagen
 * base_time_ms = "Basetime in Seconds" × 1000
 */
class WaScoringTableSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ── SCM (25m) 2025 – Männer ────────────────────────────────────
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>   50, 'base_time_ms' =>   19900],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>  100, 'base_time_ms' =>   44840],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>  200, 'base_time_ms' =>   98610],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>  400, 'base_time_ms' =>  212250],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>  800, 'base_time_ms' =>  440460],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'F', 'distance_m' => 1500, 'base_time_ms' =>  846880],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'R', 'distance_m' =>   50, 'base_time_ms' =>   22110],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'R', 'distance_m' =>  100, 'base_time_ms' =>   48330],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'R', 'distance_m' =>  200, 'base_time_ms' =>  105630],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'B', 'distance_m' =>   50, 'base_time_ms' =>   24950],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'B', 'distance_m' =>  100, 'base_time_ms' =>   55280],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'B', 'distance_m' =>  200, 'base_time_ms' =>  120160],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'S', 'distance_m' =>   50, 'base_time_ms' =>   21320],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'S', 'distance_m' =>  100, 'base_time_ms' =>   47710],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'S', 'distance_m' =>  200, 'base_time_ms' =>  106850],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'L', 'distance_m' =>  100, 'base_time_ms' =>   49280],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'L', 'distance_m' =>  200, 'base_time_ms' =>  108880],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'M', 'discipline' => 'L', 'distance_m' =>  400, 'base_time_ms' =>  234810],

            // ── SCM (25m) 2025 – Frauen ────────────────────────────────────
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>   50, 'base_time_ms' =>   22830],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>  100, 'base_time_ms' =>   50250],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>  200, 'base_time_ms' =>  110310],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>  400, 'base_time_ms' =>  230250],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>  800, 'base_time_ms' =>  477420],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'F', 'distance_m' => 1500, 'base_time_ms' =>  908240],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'R', 'distance_m' =>   50, 'base_time_ms' =>   25230],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'R', 'distance_m' =>  100, 'base_time_ms' =>   54020],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'R', 'distance_m' =>  200, 'base_time_ms' =>  118040],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'B', 'distance_m' =>   50, 'base_time_ms' =>   28370],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'B', 'distance_m' =>  100, 'base_time_ms' =>   62360],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'B', 'distance_m' =>  200, 'base_time_ms' =>  132500],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'S', 'distance_m' =>   50, 'base_time_ms' =>   23940],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'S', 'distance_m' =>  100, 'base_time_ms' =>   52710],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'S', 'distance_m' =>  200, 'base_time_ms' =>  119320],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'L', 'distance_m' =>  100, 'base_time_ms' =>   55110],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'L', 'distance_m' =>  200, 'base_time_ms' =>  121630],
            ['year' => 2025, 'pool_length' => 25, 'gender' => 'F', 'discipline' => 'L', 'distance_m' =>  400, 'base_time_ms' =>  255480],

            // ── LCM (50m) 2026 – Männer ────────────────────────────────────
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>   50, 'base_time_ms' =>   20910],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>  100, 'base_time_ms' =>   46400],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>  200, 'base_time_ms' =>  102000],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>  400, 'base_time_ms' =>  219960],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'F', 'distance_m' =>  800, 'base_time_ms' =>  452120],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'F', 'distance_m' => 1500, 'base_time_ms' =>  870670],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'R', 'distance_m' =>   50, 'base_time_ms' =>   23550],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'R', 'distance_m' =>  100, 'base_time_ms' =>   51600],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'R', 'distance_m' =>  200, 'base_time_ms' =>  111920],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'B', 'distance_m' =>   50, 'base_time_ms' =>   25950],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'B', 'distance_m' =>  100, 'base_time_ms' =>   56880],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'B', 'distance_m' =>  200, 'base_time_ms' =>  125480],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'S', 'distance_m' =>   50, 'base_time_ms' =>   22270],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'S', 'distance_m' =>  100, 'base_time_ms' =>   49450],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'S', 'distance_m' =>  200, 'base_time_ms' =>  110340],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'L', 'distance_m' =>  200, 'base_time_ms' =>  112690],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'M', 'discipline' => 'L', 'distance_m' =>  400, 'base_time_ms' =>  242500],

            // ── LCM (50m) 2026 – Frauen ────────────────────────────────────
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>   50, 'base_time_ms' =>   23610],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>  100, 'base_time_ms' =>   51710],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>  200, 'base_time_ms' =>  112230],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>  400, 'base_time_ms' =>  234180],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'F', 'distance_m' =>  800, 'base_time_ms' =>  484120],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'F', 'distance_m' => 1500, 'base_time_ms' =>  920480],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'R', 'distance_m' =>   50, 'base_time_ms' =>   26860],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'R', 'distance_m' =>  100, 'base_time_ms' =>   57130],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'R', 'distance_m' =>  200, 'base_time_ms' =>  123140],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'B', 'distance_m' =>   50, 'base_time_ms' =>   29160],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'B', 'distance_m' =>  100, 'base_time_ms' =>   64130],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'B', 'distance_m' =>  200, 'base_time_ms' =>  137550],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'S', 'distance_m' =>   50, 'base_time_ms' =>   24430],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'S', 'distance_m' =>  100, 'base_time_ms' =>   54600],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'S', 'distance_m' =>  200, 'base_time_ms' =>  121810],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'L', 'distance_m' =>  200, 'base_time_ms' =>  125700],
            ['year' => 2026, 'pool_length' => 50, 'gender' => 'F', 'discipline' => 'L', 'distance_m' =>  400, 'base_time_ms' =>  263650],
        ];

        WaScoringTable::upsert(
            $rows,
            ['year', 'pool_length', 'gender', 'discipline', 'distance_m'],
            ['base_time_ms']
        );

        $this->command->info('WA Basiszeiten importiert: ' . count($rows) . ' Einträge (SCM 2025 + LCM 2026).');
    }
}
