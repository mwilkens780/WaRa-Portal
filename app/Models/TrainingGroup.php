<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class TrainingGroup extends Model
{
    use Auditable;

    const COLORS = [
        'blue'   => ['dot' => 'bg-blue-500',   'badge' => 'bg-blue-100 text-blue-700',   'border' => 'border-blue-400'],
        'green'  => ['dot' => 'bg-green-500',  'badge' => 'bg-green-100 text-green-700',  'border' => 'border-green-400'],
        'red'    => ['dot' => 'bg-red-500',    'badge' => 'bg-red-100 text-red-700',    'border' => 'border-red-400'],
        'orange' => ['dot' => 'bg-orange-500', 'badge' => 'bg-orange-100 text-orange-700', 'border' => 'border-orange-400'],
        'purple' => ['dot' => 'bg-purple-500', 'badge' => 'bg-purple-100 text-purple-700', 'border' => 'border-purple-400'],
        'teal'   => ['dot' => 'bg-teal-500',   'badge' => 'bg-teal-100 text-teal-700',   'border' => 'border-teal-400'],
        'pink'   => ['dot' => 'bg-pink-500',   'badge' => 'bg-pink-100 text-pink-700',   'border' => 'border-pink-400'],
        'indigo' => ['dot' => 'bg-indigo-500', 'badge' => 'bg-indigo-100 text-indigo-700', 'border' => 'border-indigo-400'],
    ];

    protected $fillable = ['name', 'description', 'color', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function trainers()
    {
        return $this->belongsToMany(User::class, 'training_group_trainer');
    }

    public function swimmers()
    {
        return $this->belongsToMany(User::class, 'training_group_swimmer');
    }

    public function sessions()
    {
        return $this->belongsToMany(TrainingSession::class, 'training_session_group');
    }

    /**
     * Scope: nur Gruppen, die der User sehen darf.
     * Admins: alle | Trainer: ihre Gruppen | Schwimmer: ihre Gruppen
     */
    public function scopeVisibleTo($query, User $user): void
    {
        if ($user->isAdmin()) return;

        if (in_array($user->role, ['trainer'])) {
            $query->whereHas('trainers', fn($q) => $q->where('users.id', $user->id));
        } elseif ($user->role === 'schwimmer') {
            $query->whereHas('swimmers', fn($q) => $q->where('users.id', $user->id));
        }
    }

    public function canEdit(User $user): bool
    {
        if ($user->isAdmin()) return true;
        return $this->trainers()->where('users.id', $user->id)->exists();
    }

    public function getColorDotsAttribute(): array
    {
        return self::COLORS[$this->color] ?? self::COLORS['blue'];
    }

    public function getAuditLabel(): string
    {
        return $this->name;
    }

    public function getHasMissingTrainerAttribute(): bool
    {
        if ($this->relationLoaded('trainers')) {
            return $this->trainers->isEmpty();
        }
        return ($this->trainers_count ?? 1) === 0;
    }
}
