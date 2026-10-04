<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionSignupResponse extends Model
{
    protected $fillable = [
        'competition_signup_request_id', 'user_id', 'status', 'note', 'responded_at', 'reminder_sent_at',
        'bus_booked', 'wants_overnight', 'wants_dinner', 'carpool_seats',
        'carpool_offered_by_id', 'carpool_note', 'carpool_ride_id', 'carpool_show_phone',
    ];

    /** Wer die Fahrgemeinschaft anbietet (Elternteil) */
    public function carpoolOfferedBy() { return $this->belongsTo(User::class, 'carpool_offered_by_id'); }

    /** Angebot, bei dem dieser Schwimmer mitfaehrt */
    public function carpoolRide() { return $this->belongsTo(self::class, 'carpool_ride_id'); }

    /** Mitfahrer dieses Angebots */
    public function carpoolPassengers() { return $this->hasMany(self::class, 'carpool_ride_id'); }

    public function carpoolSeatsRemaining(): int
    {
        $booked = $this->relationLoaded('carpoolPassengers') ? $this->carpoolPassengers->count() : $this->carpoolPassengers()->count();
        return max(0, (int) $this->carpool_seats - $booked);
    }

    /** Handynummer des Anbieters - nur, wenn er sie fuer dieses Angebot freigegeben hat */
    public function carpoolPhone(): ?string
    {
        if (!$this->carpool_show_phone || !$this->carpoolOfferedBy) return null;
        return $this->carpoolOfferedBy->mobile ?: ($this->carpoolOfferedBy->phone ?: null);
    }

    /** "Petra Muster (Mutter von Lena)" - so sehen Mitfahrer, wer faehrt */
    public function carpoolDriverLabel(): string
    {
        $parent = $this->carpoolOfferedBy;
        $child  = $this->user?->firstname;
        return $parent
            ? trim($parent->firstname . ' ' . $parent->lastname) . ($child ? " (Familie von {$child})" : '')
            : 'Familie von ' . ($this->user?->name ?? '?');
    }

    protected function casts(): array
    {
        return [
            'responded_at'     => 'datetime',
            'reminder_sent_at' => 'datetime',
            'bus_booked'       => 'boolean',
            'wants_overnight'  => 'boolean',
            'wants_dinner'     => 'boolean',
            'carpool_seats'    => 'integer',
            'carpool_show_phone' => 'boolean',
        ];
    }

    public function signupRequest() { return $this->belongsTo(CompetitionSignupRequest::class, 'competition_signup_request_id'); }
    public function user()          { return $this->belongsTo(User::class); }

    public function isAttending(): bool    { return $this->status === 'attending'; }
    public function isNotAttending(): bool { return $this->status === 'not_attending'; }
    public function isPending(): bool      { return $this->status === 'pending'; }
}
