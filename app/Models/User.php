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
        'name', 'firstname', 'lastname', 'email', 'password', 'role',
        'birth_date', 'phone', 'active',
        'gender', 'dsv_id', 'membership_number', 'member_since', 'training_group',
        'additional_roles', 'initial_password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'birth_date'        => 'date',
            'member_since'      => 'date',
            'active'            => 'boolean',
            'additional_roles'  => 'array',
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

    /** Check primary role only (used by auth middleware). */
    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }

    /** Check additional roles. */
    public function hasAdditionalRole(string $role): bool
    {
        return in_array($role, $this->additional_roles ?? [], true);
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
