<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Eine Einladung zu einem Kalendertermin: ein Portal-Benutzer oder ein Gast
 * (Name + E-Mail), der über einen persönlichen Link ohne Login antwortet.
 */
class CalendarEventInvitee extends Model
{
    const STATUSES = [
        'offen'      => 'Offen',
        'zugesagt'   => 'Zugesagt',
        'vielleicht' => 'Vielleicht',
        'abgesagt'   => 'Abgesagt',
    ];

    const SOURCES = [
        'vorstand' => 'Vorstand',
        'gruppe'   => 'Gruppe',
        'eltern'   => 'Eltern',
        'einzeln'  => 'Einzeln',
        'gast'     => 'Gast',
    ];

    protected $fillable = [
        'calendar_event_id', 'user_id', 'guest_name', 'guest_email', 'token', 'source',
        'status', 'comment', 'responded_at', 'responded_by_id', 'invited_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'invited_at'   => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->user) return trim($this->user->firstname . ' ' . $this->user->lastname);

        return $this->guest_name ?: (string) $this->guest_email;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }
}
