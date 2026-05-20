<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'firstname', 'lastname', 'email', 'password', 'role', 'birth_date', 'phone', 'active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'active' => 'boolean',
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

    // Role checks
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isTrainer(): bool { return $this->role === 'trainer'; }
    public function isSchwimmer(): bool { return $this->role === 'schwimmer'; }
    public function isElternteil(): bool { return $this->role === 'elternteil'; }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
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
        return match($this->role) {
            'admin' => 'Administrator',
            'trainer' => 'Trainer',
            'schwimmer' => 'Schwimmer',
            'elternteil' => 'Elternteil',
            default => $this->role,
        };
    }
}
