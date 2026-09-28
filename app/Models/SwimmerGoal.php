<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwimmerGoal extends Model
{
    protected $fillable = [
        'user_id', 'season_id', 'type', 'title',
        'discipline', 'distance', 'course',
        'target_time_ms', 'status', 'achieved', 'achieved_at', 'achieved_time_ms',
        'progress', 'notes', 'notified',
    ];

    protected function casts(): array
    {
        return [
            'achieved'         => 'boolean',
            'notified'         => 'boolean',
            'achieved_at'      => 'date',
            'distance'         => 'integer',
            'target_time_ms'   => 'integer',
            'achieved_time_ms' => 'integer',
            'progress'         => 'integer',
        ];
    }

    const DISC_LABELS = [
        'F' => 'Freistil',
        'B' => 'Brust',
        'R' => 'Rücken',
        'S' => 'Schmetterling',
        'L' => 'Lagen',
    ];

    const TYPE_LABELS = [
        'time'          => 'Zeit-Ziel',
        'qualification' => 'Qualifikation',
        'free'          => 'Freies Ziel',
    ];

    const STATUS_LABELS = [
        'open'          => 'Offen',
        'achieved'      => 'Erreicht',
        'not_achieved'  => 'Nicht erreicht',
        'cancelled'     => 'Abgebrochen',
    ];

    /**
     * Titel eines Zeit-Ziels aus seinen Bestandteilen.
     *
     * Bei einem Zeit-Ziel steht im Titel nichts, was nicht schon in den Feldern
     * stuende - deshalb wird er gebildet und nicht abgefragt. Fehlt ein Teil,
     * bleibt er weg, statt eine Luecke in den Titel zu schreiben.
     */
    public static function timeTitle(?string $discipline, ?int $distance, ?int $targetMs): string
    {
        $teile = [];
        if ($distance)   $teile[] = $distance . ' m';
        if ($discipline) $teile[] = self::DISC_LABELS[$discipline] ?? $discipline;

        $titel = implode(' ', $teile);
        if ($targetMs) {
            $titel = trim($titel . ' unter ' . SwimmingTime::formatMs($targetMs));
        }

        return $titel !== '' ? $titel : self::TYPE_LABELS['time'];
    }

    public function user()     { return $this->belongsTo(User::class); }
    public function season()   { return $this->belongsTo(Season::class); }
    public function comments() { return $this->hasMany(SwimmerGoalComment::class); }

    public function isOpen(): bool        { return ($this->status ?? 'open') === 'open'; }
    public function isAchieved(): bool    { return $this->achieved; }
    public function isClosed(): bool      { return !$this->isOpen(); }

    public function getDisciplineLabelAttribute(): string
    {
        return self::DISC_LABELS[$this->discipline] ?? ($this->discipline ?? '');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status ?? 'open'] ?? 'Offen';
    }

    public function getFormattedTargetTimeAttribute(): ?string
    {
        return $this->target_time_ms ? SwimmingTime::formatMs($this->target_time_ms) : null;
    }

    public function getFormattedAchievedTimeAttribute(): ?string
    {
        return $this->achieved_time_ms ? SwimmingTime::formatMs($this->achieved_time_ms) : null;
    }
}
