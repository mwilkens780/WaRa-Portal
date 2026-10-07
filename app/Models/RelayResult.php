<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelayResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'competition_id', 'discipline', 'distance', 'relay_legs', 'club_name', 'team_number', 'round',
        'time_ms', 'status', 'placement', 'age_group', 'gender',
    ];

    /** "4×100" – distance ist die Strecke je Schwimmer */
    public function getDistanceLabelAttribute(): string
    {
        return ($this->relay_legs ?: 4) . '×' . $this->distance;
    }

    /** Vereinsmannschaft, z. B. "SG Wasserratten Norderstedt 2" */
    public function getTeamLabelAttribute(): string
    {
        return $this->club_name . ($this->team_number > 1 ? ' ' . $this->team_number : '');
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function members()
    {
        return $this->hasMany(RelayMember::class)->orderBy('leg');
    }
}
