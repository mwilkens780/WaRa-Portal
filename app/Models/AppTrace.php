<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppTrace extends Model
{
    const UPDATED_AT = null;

    const LEVEL_ERROR   = 1;
    const LEVEL_WARNING = 2;

    protected $fillable = ['level', 'message', 'context'];

    protected function casts(): array
    {
        return [
            'context'    => 'array',
            'level'      => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function getLevelLabelAttribute(): string
    {
        return match($this->level) {
            self::LEVEL_ERROR   => 'Fehler',
            self::LEVEL_WARNING => 'Warnung',
            default             => "Level {$this->level}",
        };
    }

    public function getLevelColorAttribute(): string
    {
        return match($this->level) {
            self::LEVEL_ERROR   => 'bg-red-100 text-red-700',
            self::LEVEL_WARNING => 'bg-amber-100 text-amber-700',
            default             => 'bg-gray-100 text-gray-600',
        };
    }
}
