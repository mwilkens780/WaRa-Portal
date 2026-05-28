<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionLog extends Model
{
    const UPDATED_AT = null;   // immutable — no updated_at managed

    protected $fillable = [
        'user_id', 'user_name', 'action', 'model_type',
        'model_id', 'model_label', 'changes', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'created' => 'Erstellt',
            'updated' => 'Geändert',
            'deleted' => 'Gelöscht',
            default   => $this->action,
        };
    }

    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            'created' => 'bg-green-100 text-green-700',
            'updated' => 'bg-blue-100 text-blue-700',
            'deleted' => 'bg-red-100 text-red-700',
            default   => 'bg-gray-100 text-gray-600',
        };
    }
}
