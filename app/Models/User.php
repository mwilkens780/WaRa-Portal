<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Auditable;

    protected array $auditHidden = ['password', 'remember_token', 'initial_password'];

    const ROLES = ['admin', 'trainer', 'schwimmer', 'elternteil', 'kampfrichter', 'vorstand'];

    const ROLE_LABELS = [
        'admin'        => 'Administrator',
        'trainer'      => 'Trainer',
        'schwimmer'    => 'Schwimmer',
        'elternteil'   => 'Elternteil',
        'kampfrichter' => 'Kampfrichter',
        'vorstand'     => 'Vorstand',
    ];

    protected $fillable = [
        'name', 'firstname', 'lastname', 'email', 'email2', 'password', 'role',
        'birth_date', 'phone', 'mobile', 'active',
        'gender', 'dsv_id', 'membership_number', 'member_since', 'training_group',
        'street', 'postal_code', 'city', 'country',
        'initial_password',
        'trainer_license_nr', 'trainer_license_valid_until',
        'rescue_certificate_until', 'first_aid_until',
        'police_clearance_date', 'notes',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'birth_date'                  => 'date',
            'member_since'                => 'date',
            'active'                      => 'boolean',
            'trainer_license_valid_until' => 'date',
            'rescue_certificate_until'    => 'date',
            'first_aid_until'             => 'date',
            'police_clearance_date'       => 'date',
        ];
    }

    // When firstname/lastname are present, derive display name from them
    public function getNameAttribute($value): string
    {
        $first = $this->attributes['firstname'] ?? '';
        $last  = $this->attributes['lastname'] ?? '';
        if ($first !== '' || $last !== '') {
            return trim("$first $last");
        }
        return $value ?? '';
    }

    // Role checks — primary role
    public function isAdmin(): bool        { return $this->role === 'admin'; }
    public function isTrainer(): bool      { return $this->role === 'trainer'; }
    public function isSchwimmer(): bool    { return $this->role === 'schwimmer'; }
    public function isElternteil(): bool   { return $this->role === 'elternteil'; }
    public function isKampfrichter(): bool { return $this->role === 'kampfrichter'; }
    public function isVorstand(): bool     { return $this->role === 'vorstand'; }

    public function hasInitialPassword(): bool
    {
        return !empty($this->attributes['initial_password']);
    }

    /**
     * Check portal access role (used by auth middleware).
     * For checking any club role, use hasAnyRole().
     */
    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }

    /** True if user has this role in user_roles table OR as portal role. */
    public function hasAnyRole(string $role): bool
    {
        if ($this->role === $role) return true;
        return $this->userRoles->contains('role', $role);
    }

    /** All club roles (from user_roles table). */
    public function userRoles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** Sync club roles: replaces entire set. */
    public function syncRoles(array $roles): void
    {
        $this->userRoles()->whereNotIn('role', $roles)->delete();
        foreach ($roles as $role) {
            $this->userRoles()->firstOrCreate(['role' => $role]);
        }
    }

    // Relations
    public function trainingSessions()
    {
        return $this->hasMany(TrainingSession::class, 'trainer_id');
    }

    public function attendances()
    {
        return $this->hasMany(TrainingAttendance::class);
    }

    public function swimmingTimes()
    {
        return $this->hasMany(SwimmingTime::class);
    }

    public function competitionResults()
    {
        return $this->hasMany(CompetitionResult::class);
    }

    public function trainingGroups()
    {
        return $this->belongsToMany(TrainingGroup::class, 'training_group_swimmer');
    }

    public function individualSessions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(TrainingSession::class, 'training_session_swimmers', 'user_id', 'training_session_id')
            ->whereNotNull('training_session_swimmers.training_session_id');
    }

    public function individualSeries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrainingSessionSwimmer::class)->whereNotNull('recurrence_group_id');
    }

    public function seriesExclusions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SwimmerSeriesExclusion::class);
    }

    public function sessionRegistrations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrainingSessionRegistration::class);
    }

    public function children()
    {
        return $this->belongsToMany(User::class, 'parent_swimmer', 'parent_id', 'swimmer_id');
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'parent_swimmer', 'swimmer_id', 'parent_id');
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }
}
