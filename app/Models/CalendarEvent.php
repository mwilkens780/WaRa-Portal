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
     * Wer eine Art anlegt, steuert die Berechtigungs-Matrix (Schlüssel events_<art>).
     * shared: Alle, die die Art anlegen dürfen, pflegen auch fremde Termine dieser Art.
     * audience: Wer eingeladen wird (null = keine Einladungen).
     */
    const TYPES = [
        'vereinstermin'    => ['label' => 'Vereinstermin',    'color' => 'emerald', 'audience' => null],
        'ehrung'           => ['label' => 'Ehrung',           'color' => 'amber', 'audience' => null],
        'meldefrist'       => ['label' => 'Meldefrist',       'color' => 'orange', 'audience' => null],
        // Vorstand lädt Vorstand ein (+ Gäste)
        'vorstandssitzung' => ['label' => 'Vorstandssitzung', 'color' => 'purple', 'audience' => 'vorstand', 'shared' => true],
        // Trainer laden Eltern minderjähriger und volljährige Gruppenmitglieder ein (+ Gäste)
        'elternabend'      => ['label' => 'Elternabend',      'color' => 'teal', 'audience' => 'eltern'],
        // Trainingslager und gemeinsame Events: einzelne Schwimmer oder Gruppen (+ Gäste)
        'team_event'       => ['label' => 'Team-Event',       'color' => 'sky', 'audience' => 'team'],
        'sonstiges'        => ['label' => 'Sonstiges',        'color' => 'gray', 'audience' => null],
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

    /** Zielgruppen einfacher Termine (Vereinstermin, Ehrung, Meldefrist …); leer = für alle */
    public function trainingGroups()
    {
        return $this->belongsToMany(TrainingGroup::class, 'calendar_event_training_group');
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
        return array_filter(self::TYPES, fn($t, $type) => $user->canAccess('events_' . $type), ARRAY_FILTER_USE_BOTH);
    }

    // ── Rechte ──────────────────────────────────────────────────────────────

    /** Bearbeiten, einladen, Dateien pflegen */
    public function canManage(?User $user): bool
    {
        if (!$user) return false;
        if ($user->hasRole('admin') || $user->id === $this->created_by) return true;
        // Fremde Termine nur, wer diese Art selbst anlegen darf (Matrix events_<art>) …
        if (!$user->canAccess('events_' . $this->type)) return false;
        // … einfache Termine und gemeinsam gepflegte (Vorstandssitzung) dann immer,
        // Termine mit Einladungen nur mit "Termine anderer bearbeiten" (Geschäftsstelle)
        if (!$this->hasInvitations() || !empty(self::TYPES[$this->type]['shared'])) return true;

        return $user->canAccess('events_manage');
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
