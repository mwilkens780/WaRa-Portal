<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'description',
        'github_issue_number', 'github_issue_url',
        'notify_on_close', 'github_closed_at',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_close'   => 'boolean',
            'github_closed_at'  => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->github_closed_at === null;
    }

    public function typeLabel(): string
    {
        return $this->type === 'bug' ? 'Fehler' : 'Verbesserungsvorschlag';
    }
}
