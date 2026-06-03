<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class Holiday extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    public static function intersecting(Carbon $from, Carbon $to): Collection
    {
        return static::where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->orderBy('start_date')
            ->get();
    }

    public function containsDate(Carbon $date): bool
    {
        return $date->between($this->start_date, $this->end_date);
    }
}
