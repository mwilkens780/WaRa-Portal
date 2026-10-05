<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine Wertung der Wettkampffolge.
 *
 * distance: bei Staffeln die GESAMTSTRECKE (4×50 → 200), relay_legs die Anzahl
 * der Schwimmer; die Strecke je Schwimmer liefert leg_distance.
 * sort_order: Reihenfolge wie in der Quelle (DSV-Datei, Lenex, WebClub) –
 * Finals mit Nummer 101 … stehen damit dort, wo sie im Programm stehen.
 */
class CompetitionEvent extends Model
{
    protected $fillable = [
        'competition_id', 'event_number', 'sort_order', 'dsv_wertungs_id', 'session_number', 'session_date',
        'session_name', 'discipline', 'distance', 'relay_legs', 'gender', 'age_min', 'age_max',
        'exercise', 'age_group', 'qualifying_time_ms', 'qualifying_deadline', 'meldegeld',
    ];

    protected function casts(): array
    {
        return [
            'session_date'        => 'date',
            'distance'            => 'integer',
            'relay_legs'          => 'integer',
            'sort_order'          => 'integer',
            'age_min'             => 'integer',
            'age_max'             => 'integer',
            'qualifying_time_ms'  => 'integer',
            'qualifying_deadline' => 'date',
            'meldegeld'           => 'decimal:2',
        ];
    }

    /** Programmreihenfolge: wie in der Quelle, ältere Daten ohne sort_order nach Abschnitt und Nummer */
    public function scopeInProgramOrder($query)
    {
        return $query->orderByRaw('sort_order IS NULL')->orderBy('sort_order')
            ->orderBy('session_number')->orderBy('event_number')->orderBy('id');
    }

    /** Strecke je Schwimmer (bei Einzelstrecken = distance) */
    public function getLegDistanceAttribute(): int
    {
        return $this->relay_legs > 1 ? intdiv((int) $this->distance, (int) $this->relay_legs) : (int) $this->distance;
    }

    public function getFormattedQualifyingTimeAttribute(): ?string
    {
        if (!$this->qualifying_time_ms) return null;
        return SwimmingTime::formatMs($this->qualifying_time_ms);
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
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
        } . ($this->exercise ? ' (' . \App\Support\Exercise::label($this->exercise) . ')' : '');
    }

    public function getDistanceLabelAttribute(): string
    {
        if ($this->relay_legs && $this->relay_legs > 1) {
            return $this->relay_legs . '×' . $this->leg_distance;
        }
        return (string)$this->distance;
    }

    public function getGenderLabelAttribute(): string
    {
        return \App\Support\Gender::title($this->gender) ?? 'Gemischt';
    }

    public function getLabelAttribute(): string
    {
        $parts = [$this->distance_label . ' m', $this->discipline_label];
        if ($this->age_group) $parts[] = $this->age_group;
        if ($this->gender !== 'X') $parts[] = \App\Support\Gender::toDsv($this->gender);
        return implode(' · ', $parts);
    }
}
