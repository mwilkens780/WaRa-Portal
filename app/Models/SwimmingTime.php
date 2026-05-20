<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SwimmingTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'training_session_id', 'discipline', 'distance', 'time_ms', 'is_personal_best', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_personal_best' => 'boolean',
            'distance' => 'integer',
            'time_ms' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trainingSession()
    {
        return $this->belongsTo(TrainingSession::class);
    }

    // Scope: Bestzeiten im aktuellen Kalenderjahr
    public function scopeThisYear($query)
    {
        return $query->whereYear('created_at', now()->year);
    }

    // Scope: Bestzeiten in der aktuellen Saison
    // Sommersaison: April–September | Wintersaison: Oktober–März
    public function scopeThisSeason($query)
    {
        $month = now()->month;
        if ($month >= 4 && $month <= 9) {
            // Sommersaison: 1. April – 30. September dieses Jahres
            return $query->whereBetween('created_at', [
                now()->startOfYear()->addMonths(3),
                now()->startOfYear()->addMonths(8)->endOfMonth(),
            ]);
        }
        // Wintersaison: 1. Oktober – 31. März
        $start = $month >= 10
            ? now()->startOfYear()->addMonths(9)
            : now()->subYear()->startOfYear()->addMonths(9);
        return $query->whereBetween('created_at', [
            $start,
            $start->copy()->addMonths(6)->endOfMonth(),
        ]);
    }

    public static function currentSeasonLabel(): string
    {
        $month = now()->month;
        $year  = now()->year;
        if ($month >= 4 && $month <= 9) {
            return "Sommersaison {$year}";
        }
        $winter = $month >= 10 ? $year : $year - 1;
        return "Wintersaison {$winter}/" . ($winter + 1);
    }

    public function getFormattedTimeAttribute(): string
    {
        return self::formatMs($this->time_ms);
    }

    public static function formatMs(int $ms): string
    {
        $minutes = intdiv($ms, 60000);
        $seconds = intdiv($ms % 60000, 1000);
        $centiseconds = intdiv($ms % 1000, 10);
        if ($minutes > 0) {
            return sprintf('%d:%02d,%02d', $minutes, $seconds, $centiseconds);
        }
        return sprintf('%d,%02d', $seconds, $centiseconds);
    }

    public function getDisciplineLabelAttribute(): string
    {
        return match($this->discipline) {
            'freistil' => 'Freistil',
            'brust' => 'Brust',
            'ruecken' => 'Rücken',
            'schmetterling' => 'Schmetterling',
            'lagen' => 'Lagen',
            default => $this->discipline,
        };
    }
}
