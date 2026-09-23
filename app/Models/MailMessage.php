<?php

namespace App\Models;

use App\Support\MailTopic;
use Illuminate\Database\Eloquent\Model;

/**
 * Protokoll jeder Mail, die das Portal verschickt oder verschicken wollte -
 * auch der abgelehnten und der gescheiterten. Zugleich Warteschlange fuer den
 * Massenversand.
 */
class MailMessage extends Model
{
    protected $fillable = [
        'user_id', 'topic', 'recipient_email', 'recipient_name', 'sent_to',
        'subject', 'mailable', 'payload', 'status', 'error', 'attempts',
        'triggered_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'  => 'array',
            'sent_at'  => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public const STATUS_LABELS = [
        'pending' => 'wartet',
        'sent'    => 'versendet',
        'failed'  => 'fehlgeschlagen',
        'skipped' => 'nicht gewünscht',
    ];

    public const STATUS_BADGES = [
        'pending' => 'bg-amber-100 text-amber-700',
        'sent'    => 'bg-green-100 text-green-700',
        'failed'  => 'bg-red-100 text-red-700',
        'skipped' => 'bg-gray-100 text-gray-500',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-gray-100 text-gray-500';
    }

    public function topicLabel(): string
    {
        return MailTopic::label($this->topic);
    }

    /** Wurde im Wartungsmodus umgeleitet? */
    public function wasRedirected(): bool
    {
        return $this->sent_to !== null && $this->sent_to !== $this->recipient_email;
    }
}
