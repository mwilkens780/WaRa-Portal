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
        // 1. Exact date match: today falls within a season
        $exact = static::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->orderByDesc('start_date')
            ->first();
        if ($exact) return $exact;

        // 2. Gap between seasons (e.g. summer holidays): return the next upcoming season.
        //    This ensures the new season is already "current" as soon as the previous one ends.
        $next = static::where('start_date', '>', now())
            ->orderBy('start_date')
            ->first();
        if ($next) return $next;

        // 3. Last resort: the explicitly flagged season (e.g. before any season exists)
        return static::where('is_current', true)->first();
    }

    public static function forDate(Carbon $date): ?self
    {
        return static::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /** Season immediately following the given one (by start_date). */
    public static function after(self $season): ?self
    {
        return static::where('start_date', '>', $season->end_date)
            ->orderBy('start_date')
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
