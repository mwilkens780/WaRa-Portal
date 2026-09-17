<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultDiscrepancy extends Model
{
    protected $fillable = [
        'competition_id', 'user_id',
        'competition_result_id', 'conflicting_result_id',
        'discipline', 'distance',
        'field', 'source_a', 'value_a', 'source_b', 'value_b',
        'message', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'distance'    => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function result()
    {
        return $this->belongsTo(CompetitionResult::class, 'competition_result_id');
    }

    public function conflictingResult()
    {
        return $this->belongsTo(CompetitionResult::class, 'conflicting_result_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }

    /** Menschenlesbarer Feldname für die Anzeige. */
    public function getFieldLabelAttribute(): string
    {
        return match ($this->field) {
            'time_ms'          => 'Zeit',
            'placement'        => 'Platzierung',
            'discipline'       => 'Disziplin',
            'distance'         => 'Strecke',
            'gender'           => 'Geschlecht',
            'age_group'        => 'Altersklasse',
            'is_personal_best' => 'Persönliche Bestzeit',
            'is_final'         => 'Finale',
            'wa_points'        => 'WA-Punkte',
            default            => $this->field,
        };
    }
}
