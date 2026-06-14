<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'competition_id', 'club_name', 'athlete_lastname', 'athlete_firstname',
        'birth_year', 'gender', 'dsv_id', 'event_number', 'entry_time_ms',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }
}
