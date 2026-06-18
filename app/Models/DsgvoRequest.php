<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DsgvoRequest extends Model
{
    protected $fillable = [
        'user_id', 'requester_name', 'requester_email',
        'type', 'description', 'status', 'admin_notes', 'responded_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static array $types = [
        'auskunft'       => 'Auskunft (Art. 15)',
        'berichtigung'   => 'Berichtigung (Art. 16)',
        'loeschung'      => 'Löschung (Art. 17)',
        'portabilitaet'  => 'Datenübertragbarkeit (Art. 20)',
        'widerspruch'    => 'Widerspruch (Art. 21)',
    ];

    public static array $statuses = [
        'offen'          => ['label' => 'Offen',           'color' => 'bg-amber-100 text-amber-800'],
        'in_bearbeitung' => ['label' => 'In Bearbeitung',  'color' => 'bg-blue-100 text-blue-800'],
        'abgeschlossen'  => ['label' => 'Abgeschlossen',   'color' => 'bg-green-100 text-green-800'],
    ];

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'bg-gray-100 text-gray-800';
    }

    /** Deadline: DSGVO requires response within 30 days (Art. 12 Abs. 3). */
    public function deadline(): \Carbon\Carbon
    {
        return $this->created_at->addDays(30);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'abgeschlossen' && $this->deadline()->isPast();
    }
}
