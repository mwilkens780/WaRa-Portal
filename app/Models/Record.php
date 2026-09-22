<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Record extends Model
{
    protected $fillable = [
        'type', 'discipline', 'distance', 'gender', 'age_group', 'birth_year', 'course',
        'swimmer_name', 'user_id', 'time_ms', 'set_date', 'location',
        'competition_result_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'set_date'   => 'date',
            'time_ms'    => 'integer',
            'distance'   => 'integer',
        ];
    }

    /**
     * Strecken der Vereinsrekordliste, je Geschlecht und Bahn.
     *
     * Nur Einzelstrecken ab 50 m, keine Staffeln. 100 m Lagen gibt es nur auf
     * der Kurzbahn. Vereinsrekorde kennen ausschliesslich die offene Wertung
     * (age_group = NULL) - jede Zeit gilt jahrgangsuebergreifend.
     * Festgelegt mit dem Verein; nicht ohne Ruecksprache erweitern.
     */
    public const VR_EVENTS = [
        'Langbahn' => [
            'F' => [50, 100, 200, 400, 800, 1500],
            'R' => [50, 100, 200],
            'B' => [50, 100, 200],
            'S' => [50, 100, 200],
            'L' => [200, 400],
        ],
        'Kurzbahn' => [
            'F' => [50, 100, 200, 400, 800, 1500],
            'R' => [50, 100, 200],
            'B' => [50, 100, 200],
            'S' => [50, 100, 200],
            'L' => [100, 200, 400],
        ],
    ];

    public static function isVrEvent(?string $discipline, ?int $distance, ?string $course): bool
    {
        return in_array((int) $distance, self::VR_EVENTS[$course][$discipline] ?? [], true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function competitionResult()
    {
        return $this->belongsTo(CompetitionResult::class);
    }

    public function getFormattedTimeAttribute(): string
    {
        return SwimmingTime::formatMs($this->time_ms);
    }

    public function getDisciplineLabelAttribute(): string
    {
        return match($this->discipline) {
            'F' => 'Freistil',
            'B' => 'Brust',
            'R' => 'Rücken',
            'S' => 'Schmetterling',
            'L' => 'Lagen',
            default => $this->discipline,
        };
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'M' ? 'Männlich' : 'Weiblich';
    }

    public function getTypeLabel(): string
    {
        return $this->type === 'vereinsrekord' ? 'VR' : 'LR';
    }

    public function getAgeGroupLabelAttribute(): string
    {
        return $this->age_group ?: 'Offene Klasse';
    }
}
