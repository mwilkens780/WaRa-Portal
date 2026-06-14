<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonScore extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'athlete_id', 'user_id', 'season_year',
        'total_score', 'podiums', 'finals', 'personal_bests', 'club_records',
        'recalculated_at',
    ];

    protected function casts(): array
    {
        return ['recalculated_at' => 'datetime'];
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
