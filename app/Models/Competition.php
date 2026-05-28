<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Competition extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name', 'location', 'date', 'date_end', 'meldeschluss', 'type', 'description', 'organizer', 'course', 'season_id',
    ];

    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'date_end'     => 'date',
            'meldeschluss' => 'date',
        ];
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function trainingGroups()
    {
        return $this->belongsToMany(TrainingGroup::class, 'competition_training_group');
    }

    public function results()
    {
        return $this->hasMany(CompetitionResult::class);
    }

    public function events()
    {
        return $this->hasMany(CompetitionEvent::class)
            ->orderBy('session_number')
            ->orderBy('event_number');
    }

    public function getCourseLabelAttribute(): string
    {
        return match($this->course) {
            'LCM' => '50 m Langbahn',
            'SCM' => '25 m Kurzbahn',
            default => '–',
        };
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'competition_results')
            ->distinct();
    }

    const TYPE_LABELS = [
        'vereinsintern'  => 'Vereinsintern',
        'regional'       => 'Regional',
        'national'       => 'National',
        'international'  => 'International',
        'meisterschaften'=> 'Meisterschaften',
        'einladung'      => 'Einladung',
        'nop'            => 'NOP',
        'dms'            => 'DMS',
        'shsv'           => 'SHSV',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getDateRangeAttribute(): string
    {
        if ($this->date_end && $this->date_end->ne($this->date)) {
            return $this->date->format('d.m.Y') . ' – ' . $this->date_end->format('d.m.Y');
        }
        return $this->date->format('d.m.Y');
    }
}
