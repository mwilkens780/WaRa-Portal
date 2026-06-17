<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionSignupResponse extends Model
{
    protected $fillable = [
        'competition_signup_request_id', 'user_id', 'status', 'note', 'responded_at', 'reminder_sent_at',
        'bus_booked', 'wants_overnight', 'wants_dinner', 'carpool_seats',
    ];

    protected function casts(): array
    {
        return [
            'responded_at'     => 'datetime',
            'reminder_sent_at' => 'datetime',
            'bus_booked'       => 'boolean',
            'wants_overnight'  => 'boolean',
            'wants_dinner'     => 'boolean',
            'carpool_seats'    => 'integer',
        ];
    }

    public function signupRequest() { return $this->belongsTo(CompetitionSignupRequest::class, 'competition_signup_request_id'); }
    public function user()          { return $this->belongsTo(User::class); }

    public function isAttending(): bool    { return $this->status === 'attending'; }
    public function isNotAttending(): bool { return $this->status === 'not_attending'; }
    public function isPending(): bool      { return $this->status === 'pending'; }
}
