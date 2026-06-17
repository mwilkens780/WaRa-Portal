<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionSignupRequest extends Model
{
    protected $fillable = [
        'competition_id', 'status', 'message', 'attachment_path',
        'deadline', 'eligible_group_ids', 'eligible_user_ids',
        'created_by_id', 'activated_at', 'closed_at',
        'meeting_point', 'meeting_time', 'bus_available', 'bus_seats',
        'offer_overnight', 'offer_dinner',
    ];

    protected function casts(): array
    {
        return [
            'eligible_group_ids' => 'array',
            'eligible_user_ids'  => 'array',
            'deadline'           => 'date',
            'activated_at'       => 'datetime',
            'closed_at'          => 'datetime',
            'bus_available'      => 'boolean',
            'bus_seats'          => 'integer',
            'offer_overnight'    => 'boolean',
            'offer_dinner'       => 'boolean',
        ];
    }

    public function busBookedCount(): int
    {
        return $this->responses()->where('bus_booked', true)->count();
    }

    public function busSeatsRemaining(): int
    {
        return max(0, ($this->bus_seats ?? 8) - $this->busBookedCount());
    }

    public function competition() { return $this->belongsTo(Competition::class); }
    public function createdBy()   { return $this->belongsTo(User::class, 'created_by_id'); }
    public function responses()   { return $this->hasMany(CompetitionSignupResponse::class); }

    public function isDraft(): bool  { return $this->status === 'draft'; }
    public function isActive(): bool { return $this->status === 'active'; }
    public function isClosed(): bool { return $this->status === 'closed'; }

    // All users eligible to respond (from groups + individual users)
    public function eligibleUsers(): \Illuminate\Support\Collection
    {
        $userIds = collect();

        if (!empty($this->eligible_group_ids)) {
            $groupUserIds = User::whereHas('trainingGroups', fn($q) =>
                $q->whereIn('training_groups.id', $this->eligible_group_ids)
            )->where('active', true)->pluck('id');
            $userIds = $userIds->merge($groupUserIds);
        }

        if (!empty($this->eligible_user_ids)) {
            $userIds = $userIds->merge($this->eligible_user_ids);
        }

        return User::whereIn('id', $userIds->unique())->orderBy('lastname')->orderBy('firstname')->get();
    }
}
