<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Competition extends Model
{
    use HasFactory, Auditable;

    const LEVEL_LABELS = [
        'dsv_dm'    => 'Deutsche Meisterschaften (DM)',
        'dsv_djm'   => 'Deutsche Jahrgangsmeisterschaften (DJM)',
        'nsv'       => 'Norddeutsche Meisterschaften (NDM)',
        'shsv_lm'   => 'Schleswig-Holsteinische Landesmeisterschaften',
        'shsv_open' => 'Offene SHSV-Veranstaltung',
        'vereins'   => 'Vereinswettkampf',
    ];

    protected $fillable = [
        'name', 'location', 'date', 'date_end', 'meldeschluss', 'type', 'description',
        'organizer', 'course', 'season_id', 'organisation_notes',
        'ausrichter', 'venue_details', 'kampfgericht', 'contact_info',
        'announcement_pdf_path', 'announcement_data',
        'source_file', 'source_url', 'import_hash', 'level', 'federation_id',
        'analysis_text',
    ];

    protected function casts(): array
    {
        return [
            'date'               => 'date',
            'date_end'           => 'date',
            'meldeschluss'       => 'date',
            'organisation_notes' => 'array',
            'venue_details'      => 'array',
            'kampfgericht'       => 'array',
            'contact_info'       => 'array',
            'announcement_data'  => 'array',
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

    public function signupRequest()
    {
        return $this->hasOne(CompetitionSignupRequest::class);
    }

    public function documents()
    {
        return $this->hasMany(\App\Models\CompetitionDocument::class)->orderBy('category')->orderBy('created_at');
    }

    public function federation()
    {
        return $this->belongsTo(Federation::class);
    }

    public function entries()
    {
        return $this->hasMany(CompetitionEntry::class);
    }

    public function relayEntries()
    {
        return $this->hasMany(CompetitionRelayEntry::class);
    }

    public function extResults()
    {
        return $this->hasMany(ExtCompetitionResult::class);
    }

    public function relayResults()
    {
        return $this->hasMany(RelayResult::class);
    }

    public function importLogs()
    {
        return $this->hasMany(ImportLog::class);
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVEL_LABELS[$this->level] ?? ($this->level ?? '–');
    }

    public function getCourseLabelAttribute(): string
    {
        return match($this->course) {
            'Langbahn' => 'Langbahn',
            'Kurzbahn' => 'Kurzbahn',
            default    => '–',
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
