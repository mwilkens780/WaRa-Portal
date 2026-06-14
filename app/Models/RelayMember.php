<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelayMember extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = ['relay_result_id', 'athlete_id', 'leg'];

    public function relayResult()
    {
        return $this->belongsTo(RelayResult::class);
    }

    public function athlete()
    {
        return $this->belongsTo(Athlete::class);
    }
}
