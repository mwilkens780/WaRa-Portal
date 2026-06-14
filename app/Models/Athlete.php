<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Athlete extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lastname', 'firstname', 'birth_year', 'gender',
        'nationality', 'club_name', 'user_id', 'swimrankings_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function extResults()
    {
        return $this->hasMany(ExtCompetitionResult::class);
    }

    public function relayMemberships()
    {
        return $this->hasMany(RelayMember::class);
    }

    public function seasonScores()
    {
        return $this->hasMany(SeasonScore::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }
}
