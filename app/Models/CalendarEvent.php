<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Kalendertermin. Einfache Termine (Vereinstermin, Meldefrist …) stehen nur im
 * Kalender; Termine mit Einladung (Vorstandssitzung, Elternabend, Team-Event)
 * haben Eingeladene, Anmeldung, Agenda, Anhänge und Protokolle.
 *
 * Sichtbarkeit (Entscheidung Martin, 06.10.2026): Den Termin sehen alle im
 * Kalender, Agenda/Anhänge/Protokolle nur Eingeladene, Ersteller und Admin.
 */
class CalendarEvent extends Model
{
    use Auditable;

    /**
     * creators: Rollen (Portal- oder Vereinsrolle), die diese Art anlegen dürfen.
     * audience: Wer eingeladen wird (null = keine Einladungen).
     */
    const TYPES = [
        'vereinstermin'    => ['label' => 'Vereinstermin',    'color' => 'emerald', 'creators' => ['trainer', 'admin'], 'audience' => null],
        'ehrung'           => ['label' => 'Ehrung',           'color' => 'amber',   'creators' => ['trainer', 'admin'], 'audience' => null],
        'meldefrist'       => ['label' => 'Meldefrist',       'color' => 'orange',  'creators' => ['trainer', 'admin'], 'audience' => null],
        // Vorstand lädt Vorstand ein (+ Gäste)
        'vorstandssitzung' => ['label' => 'Vorstandssitzung', 'color' => 'purple',  'creators' => ['vorstand', 'admin'], 'audience' => 'vorstand'],
        // Trainer laden Eltern minderjähriger und volljährige Gruppenmitglieder ein (+ Gäste)
        'elternabend'      => ['label' => 'Elternabend',      'color' => 'teal',    'creators' => ['trainer', 'admin'], 'audience' => 'eltern'],
        // Trainingslager und gemeinsame Events: einzelne Schwimmer oder Gruppen (+ Gäste)
        'team_event'       => ['label' => 'Team-Event',       'color' => 'sky',     'creators' => ['vorstand', 'trainer', 'schwimmer', 'admin'], 'audience' => 'team'],
        'sonstiges'        => ['label' => 'Sonstiges',        'color' => 'gray',    'creators' => ['trainer', 'admin'], 'audience' => null],
    ];

    protected $fillable = [
        'title', 'description', 'location', 'agenda', 'start_date', 'end_date',
        'start_time', 'end_time', 'type', 'season_id', 'created_by',
        'rsvp_enabled', 'rsvp_deadline', 'capacity', 'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date'       => 'date',
            'end_date'         => 'date',
            'rsvp_enabled'     => 'boolean',
            'rsvp_deadline'    => 'date',
            'capacity'         => 'integer',
            'reminder_sent_at' => 'datetime',
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

    public function invitees()
    {
        return $this->hasMany(CalendarEventInvitee::class);
    }

    public function files()
    {
        return $this->hasMany(CalendarEventFile::class)->orderBy('created_at');
    }

    // ── Art ─────────────────────────────────────────────────────────────────

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPES[$this->type]['color'] ?? 'gray';
    }

    /** vorstand | eltern | team | null */
    public function getAudienceAttribute(): ?string
    {
        return self::TYPES[$this->type]['audience'] ?? null;
    }

    public function hasInvitations(): bool
    {
        return $this->audience !== null;
    }

    /** Terminarten, die dieser Benutzer anlegen darf */
    public static function creatableTypesFor(User $user): array
    {
        return array_filter(self::TYPES, fn($t) => self::userHasAnyRole($user, $t['creators']));
    }

    public static function userHasAnyRole(User $user, array $roles): bool
    {
        foreach ($roles as $role) {
            if ($user->hasAnyRole($role)) return true;
        }
        return false;
    }

    // ── Rechte ──────────────────────────────────────────────────────────────

    /** Bearbeiten, einladen, Dateien pflegen */
    public function canManage(?User $user): bool
    {
        if (!$user) return false;
        if ($user->hasRole('admin') || $user->id === $this->created_by) return true;
        // Vorstandssitzungen pflegt der ganze Vorstand
        if ($this->type === 'vorstandssitzung') return $user->hasAnyRole('vorstand');
        // Einfache Termine wie bisher: Trainer
        if (!$this->hasInvitations()) return $user->hasRole('trainer');

        return false;
    }

    /** Agenda, Anhänge, Protokolle, Teilnehmer sehen */
    public function canSeeDetails(?User $user): bool
    {
        if (!$this->hasInvitations()) return true;
        if (!$user) return false;
        if ($this->canManage($user)) return true;

        return $this->invitationsFor($user)->isNotEmpty();
    }

    /**
     * Einladungen, auf die dieser Benutzer antworten darf: die eigene und die
     * seiner minderjährigen Kinder (Team-Events laden Schwimmer ein, Eltern
     * sagen für sie zu – wie bei Wettkampf-Abfragen).
     */
    public function invitationsFor(User $user)
    {
        $childIds = $user->wards()->pluck('id')->all();

        return $this->invitees()
            ->whereIn('user_id', array_merge([$user->id], $childIds))
            ->with('user:id,firstname,lastname')
            ->get();
    }

    public function acceptedCount(): int
    {
        return $this->invitees()->where('status', 'zugesagt')->count();
    }

    public function isFull(): bool
    {
        return $this->capacity && $this->acceptedCount() >= $this->capacity;
    }

    public function rsvpOpen(): bool
    {
        return $this->rsvp_enabled
            && (!$this->rsvp_deadline || today()->lte($this->rsvp_deadline))
            && today()->lte($this->end_date ?? $this->start_date);
    }

    public function getWhenLabelAttribute(): string
    {
        $s = $this->start_date->isoFormat('dd, D. MMMM YYYY');
        if ($this->end_date && !$this->end_date->isSameDay($this->start_date)) {
            $s .= ' – ' . $this->end_date->isoFormat('dd, D. MMMM YYYY');
        }
        if ($this->start_time) {
            $s .= ', ' . substr($this->start_time, 0, 5) . ($this->end_time ? '–' . substr($this->end_time, 0, 5) : '') . ' Uhr';
        }
        return $s;
    }

    public function getAuditLabel(): string
    {
        return $this->title;
    }
}
