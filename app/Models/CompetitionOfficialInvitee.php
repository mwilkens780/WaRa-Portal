<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Angefragte Person einer Kampfrichter-Abfrage mit ihrer Rückmeldung:
 * je Veranstaltungstag ja/nein mit Kommentar, dazu Wunschpositionen.
 */
class CompetitionOfficialInvitee extends Model
{
    protected $fillable = [
        'competition_official_request_id', 'user_id', 'availability', 'positions', 'comment',
        'invited_at', 'responded_at', 'kari_group',
    ];

    protected function casts(): array
    {
        return [
            'availability' => 'array',
            'positions'    => 'array',
            'invited_at'   => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function request()
    {
        return $this->belongsTo(CompetitionOfficialRequest::class, 'competition_official_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(CompetitionOfficialAssignment::class)->orderBy('session_number');
    }

    /** Gruppe für KARIMELDUNG: festgelegt oder aus der ersten Wunschposition vorgeschlagen */
    public function getGroupAttribute(): string
    {
        if ($this->kari_group) return $this->kari_group;
        $first = $this->assignments->first()?->position ?? ($this->positions[0] ?? null);

        return match ($first) {
            'SCH', 'ASCH' => 'SCH',
            'AUS'         => 'AUS',
            'SP'          => 'SPR',
            default       => 'WKR',
        };
    }

    /** true/false je Tag, null = keine Angabe */
    public function availableOn(string $day): ?bool
    {
        $v = $this->availability[$day]['available'] ?? null;
        return $v === null ? null : (bool) $v;
    }

    public function commentOn(string $day): ?string
    {
        return $this->availability[$day]['comment'] ?? null;
    }

    public function availableAnyDay(): bool
    {
        return collect($this->availability ?? [])->contains(fn($d) => !empty($d['available']));
    }
}
