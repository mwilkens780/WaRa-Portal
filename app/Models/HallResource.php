<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HallResource extends Model
{
    protected $fillable = ['name', 'type', 'color', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    const TYPE_LABELS = [
        'lane' => 'Bahn',
        'pool' => 'Becken',
        'room' => 'Raum',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(HallBooking::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
