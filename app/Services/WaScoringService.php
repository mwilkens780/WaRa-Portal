<?php

namespace App\Services;

use App\Models\WaScoringTable;

class WaScoringService
{
    // WA points formula: 1000 * (base_time / swim_time)^3
    public function calculatePoints(
        string $discipline,
        int    $distance,
        string $gender,
        int    $timeMs,
        int    $year,
        int    $poolLength
    ): ?int {
        if ($timeMs <= 0 || !in_array($gender, ['M', 'F'])) {
            return null;
        }

        $baseTimeMs = WaScoringTable::where([
            'year'        => $year,
            'pool_length' => $poolLength,
            'gender'      => $gender,
            'discipline'  => $discipline,
            'distance_m'  => $distance,
        ])->value('base_time_ms');

        if (!$baseTimeMs) {
            return null;
        }

        return (int) round(1000 * (($baseTimeMs / $timeMs) ** 3));
    }

    public function latestYear(int $poolLength): ?int
    {
        return WaScoringTable::where('pool_length', $poolLength)->max('year');
    }

    public function poolLengthFromCourse(string $course): int
    {
        return str_contains(strtolower($course), 'lang') ? 50 : 25;
    }

    // Parse time string "1:23,45" or "23,45" → milliseconds
    public static function parseTimeInput(string $input): ?int
    {
        $input = trim(str_replace('.', ',', $input));
        if ($input === '') return null;

        if (str_contains($input, ':')) {
            [$min, $rest] = explode(':', $input, 2);
            [$sec, $cs]   = array_pad(explode(',', $rest, 2), 2, '0');
            return ((int)$min * 60000) + ((int)$sec * 1000) + ((int)str_pad($cs, 2, '0') * 10);
        }

        [$sec, $cs] = array_pad(explode(',', $input, 2), 2, '0');
        return ((int)$sec * 1000) + ((int)str_pad($cs, 2, '0') * 10);
    }
}
