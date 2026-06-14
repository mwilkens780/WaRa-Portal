<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelayResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'competition_id', 'discipline', 'distance', 'club_name',
        'time_ms', 'status', 'placement', 'age_group', 'gender',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function members()
    {
        return $this->hasMany(RelayMember::class)->orderBy('leg');
    }
}
