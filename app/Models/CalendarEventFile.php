<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Datei oder Link zu einem Termin: Agenda-Unterlage, Anhang oder Protokoll.
 * Ein Protokoll einer früheren Sitzung wird nicht kopiert, sondern über
 * source_file_id verknüpft (Download liefert die Originaldatei).
 */
class CalendarEventFile extends Model
{
    const CATEGORIES = [
        'agenda'    => 'Unterlagen zur Agenda',
        'anhang'    => 'Anhänge',
        'protokoll' => 'Protokolle',
    ];

    protected $fillable = [
        'calendar_event_id', 'category', 'title', 'path', 'original_name', 'size', 'url',
        'source_file_id', 'created_by_id',
    ];

    public function event()
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function source()
    {
        return $this->belongsTo(self::class, 'source_file_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Datei, die tatsächlich ausgeliefert wird (bei Verknüpfung die Originaldatei) */
    public function stored(): ?self
    {
        return $this->source_file_id ? $this->source : $this;
    }

    public function isLink(): bool
    {
        $s = $this->stored();
        return $s && $s->url && !$s->path;
    }

    public function getSizeLabelAttribute(): ?string
    {
        $bytes = $this->stored()?->size;
        if (!$bytes) return null;

        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '') . ' MB' : max(1, round($bytes / 1024)) . ' KB';
    }
}
