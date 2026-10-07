<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Gesuchte Position je Abschnitt (Anzahl), festgelegt vom Kampfrichterobmann */
class CompetitionOfficialNeed extends Model
{
    protected $fillable = ['competition_official_request_id', 'session_number', 'position', 'count'];

    protected function casts(): array
    {
        return ['session_number' => 'integer', 'count' => 'integer'];
    }
}
