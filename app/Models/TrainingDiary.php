<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingDiary extends Model
{
    protected $fillable = [
        'training_session_id', 'user_id', 'self_score', 'trainer_score',
    ];

    protected function casts(): array
    {
        return [
            'self_score'    => 'integer',
            'trainer_score' => 'integer',
        ];
    }

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Abweichung zwischen Selbst- und Trainereinschätzung (null wenn eine fehlt). */
    public function getDeviationAttribute(): ?int
    {
        if ($this->self_score === null || $this->trainer_score === null) return null;
        return abs($this->self_score - $this->trainer_score);
    }

    /** 'match' | 'minor' | 'major' | null */
    public function getDeviationLevelAttribute(): ?string
    {
        $d = $this->deviation;
        if ($d === null) return null;
        if ($d === 0)    return 'match';
        if ($d <= 2)     return 'minor';
        return 'major';
    }

    public function getScoreColorAttribute(): string
    {
        return self::scoreColor($this->self_score);
    }

    public static function scoreColor(?int $score): string
    {
        return match(true) {
            $score === null => 'text-gray-400',
            $score >= 8     => 'text-green-600',
            $score >= 5     => 'text-amber-500',
            default         => 'text-red-500',
        };
    }
}
