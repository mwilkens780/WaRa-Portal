<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Einsatz eines Kampfrichters in einem Abschnitt, festgelegt vom Kampfrichterobmann */
class CompetitionOfficialAssignment extends Model
{
    protected $fillable = ['competition_official_invitee_id', 'session_number', 'position'];

    protected function casts(): array
    {
        return ['session_number' => 'integer'];
    }

    public function invitee()
    {
        return $this->belongsTo(CompetitionOfficialInvitee::class, 'competition_official_invitee_id');
    }

    public function getPositionLabelAttribute(): string
    {
        return CompetitionOfficialRequest::POSITIONS[$this->position] ?? $this->position;
    }
}
