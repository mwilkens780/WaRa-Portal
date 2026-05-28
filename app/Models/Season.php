<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Season extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'is_current'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'is_current' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return static::where('is_current', true)->first()
            ?? static::where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();
    }

    public static function forDate(Carbon $date): ?self
    {
        return static::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /** Months of this season as Carbon instances (first day of each month). */
    public function months(): array
    {
        $months = [];
        $cursor = $this->start_date->copy()->startOfMonth();
        $end    = $this->end_date->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = $cursor->copy();
            $cursor->addMonth();
        }
        return $months;
    }

    public function getLabelAttribute(): string
    {
        return 'Saison ' . $this->name;
    }
}
