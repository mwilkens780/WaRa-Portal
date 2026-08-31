<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class GroupMottoWeek extends Model
{
    protected $fillable = [
        'training_group_id',
        'user_id',
        'week_start',
        'motto',
        'generated_motto',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
        ];
    }

    public function group()
    {
        return $this->belongsTo(TrainingGroup::class, 'training_group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrentWeek(): bool
    {
        $monday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        return $this->week_start->eq($monday);
    }

    public function isUpcoming(): bool
    {
        return $this->week_start->gt(now()->startOfWeek(Carbon::MONDAY)->startOfDay());
    }

    public function isPast(): bool
    {
        return $this->week_start->lt(now()->startOfWeek(Carbon::MONDAY)->startOfDay());
    }

    public function daysUntilStart(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->week_start, false);
    }

    public function hasMottoText(): bool
    {
        return !empty($this->motto);
    }
}
