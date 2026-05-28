<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use Auditable;

    const TYPES = [
        'vereinstermin'    => ['label' => 'Vereinstermin',    'color' => 'emerald'],
        'ehrung'           => ['label' => 'Ehrung',           'color' => 'amber'],
        'meldefrist'       => ['label' => 'Meldefrist',       'color' => 'orange'],
        'vorstandssitzung' => ['label' => 'Vorstandssitzung', 'color' => 'purple'],
        'sonstiges'        => ['label' => 'Sonstiges',        'color' => 'gray'],
    ];

    protected $fillable = [
        'title', 'description', 'start_date', 'end_date',
        'start_time', 'end_time', 'type', 'season_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPES[$this->type]['color'] ?? 'gray';
    }

    public function getAuditLabel(): string
    {
        return $this->title;
    }
}
