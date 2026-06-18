<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaScoringTable extends Model
{
    protected $fillable = [
        'year', 'pool_length', 'gender', 'discipline', 'distance_m', 'base_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'year'         => 'integer',
            'pool_length'  => 'integer',
            'distance_m'   => 'integer',
            'base_time_ms' => 'integer',
        ];
    }

    public static function disciplines(): array
    {
        return ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];
    }

    public static function standardDistances(string $discipline): array
    {
        return match($discipline) {
            'F'     => [50, 100, 200, 400, 800, 1500],
            'L'     => [100, 200, 400],
            default => [50, 100, 200],
        };
    }

    public function getFormattedBaseTimeAttribute(): string
    {
        $ms  = $this->base_time_ms;
        $min = intdiv($ms, 60000);
        $sec = intdiv($ms % 60000, 1000);
        $cs  = intdiv($ms % 1000, 10);
        if ($min > 0) {
            return sprintf('%d:%02d,%02d', $min, $sec, $cs);
        }
        return sprintf('%d,%02d', $sec, $cs);
    }
}
