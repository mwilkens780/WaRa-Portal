<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class TrainingGroup extends Model
{
    use Auditable;

    const GROUP_TYPES = [
        'leistungssport'    => 'Leistungssport',
        'masters'           => 'Masters',
        'nachwuchssport'    => 'Nachwuchssport',
        'breitensport'      => 'Breitensport',
        'triathlon'         => 'Triathlon',
        'kurse'             => 'Kurse',
        'synchronschwimmen' => 'Synchronschwimmen',
        'dlrg'              => 'DLRG',
    ];

    /** Pflichtfarbe je Gruppentyp – wird automatisch bei Typwahl gesetzt. */
    const TYPE_COLORS = [
        'leistungssport'    => 'blue',
        'masters'           => 'navy',
        'nachwuchssport'    => 'sky',
        'breitensport'      => 'darkgreen',
        'triathlon'         => 'red',
        'kurse'             => 'lime',
        'synchronschwimmen' => 'pink',
        'dlrg'              => 'gray',
    ];

    /** Farben, die als individuelle Abweichung wählbar sind (nicht an einen Typ gebunden). */
    const CUSTOM_COLORS = ['yellow', 'orange', 'amber', 'purple', 'teal', 'indigo'];

    const COLORS = [
        // ── Typ-gebundene Farben ─────────────────────────────────────────────
        'blue'      => ['label' => 'Mittelblau',  'dot' => 'bg-blue-500',   'badge' => 'bg-blue-100 text-blue-700',    'border' => 'border-blue-400'],
        'navy'      => ['label' => 'Dunkelblau',  'dot' => 'bg-blue-900',   'badge' => 'bg-blue-200 text-blue-900',    'border' => 'border-blue-800'],
        'sky'       => ['label' => 'Hellblau',    'dot' => 'bg-sky-400',    'badge' => 'bg-sky-100 text-sky-700',      'border' => 'border-sky-300'],
        'darkgreen' => ['label' => 'Dunkelgrün',  'dot' => 'bg-green-700',  'badge' => 'bg-green-100 text-green-900',  'border' => 'border-green-600'],
        'red'       => ['label' => 'Rot',         'dot' => 'bg-red-500',    'badge' => 'bg-red-100 text-red-700',      'border' => 'border-red-400'],
        'lime'      => ['label' => 'Hellgrün',    'dot' => 'bg-lime-500',   'badge' => 'bg-lime-100 text-lime-700',    'border' => 'border-lime-400'],
        'pink'      => ['label' => 'Weiß/Pink',   'dot' => 'bg-pink-200',   'badge' => 'bg-pink-50 text-pink-600',     'border' => 'border-pink-300'],
        'gray'      => ['label' => 'Grau',        'dot' => 'bg-gray-400',   'badge' => 'bg-gray-100 text-gray-600',    'border' => 'border-gray-300'],
        // ── Individuelle Abweichungsfarben ───────────────────────────────────
        'yellow'    => ['label' => 'Gelb',        'dot' => 'bg-yellow-400', 'badge' => 'bg-yellow-100 text-yellow-700', 'border' => 'border-yellow-300'],
        'orange'    => ['label' => 'Orange',      'dot' => 'bg-orange-500', 'badge' => 'bg-orange-100 text-orange-700', 'border' => 'border-orange-400'],
        'amber'     => ['label' => 'Bernstein',   'dot' => 'bg-amber-500',  'badge' => 'bg-amber-100 text-amber-700',  'border' => 'border-amber-400'],
        'purple'    => ['label' => 'Lila',        'dot' => 'bg-purple-500', 'badge' => 'bg-purple-100 text-purple-700', 'border' => 'border-purple-400'],
        'teal'      => ['label' => 'Türkis',      'dot' => 'bg-teal-500',   'badge' => 'bg-teal-100 text-teal-700',    'border' => 'border-teal-400'],
        'indigo'    => ['label' => 'Indigo',      'dot' => 'bg-indigo-500', 'badge' => 'bg-indigo-100 text-indigo-700', 'border' => 'border-indigo-400'],
        // ── Legacy (Altdaten, nicht im Picker sichtbar) ──────────────────────
        'green'     => ['label' => 'Grün',        'dot' => 'bg-green-500',  'badge' => 'bg-green-100 text-green-700',  'border' => 'border-green-400'],
    ];

    protected $fillable = [
        'name', 'description', 'color', 'group_type', 'active', 'webclub_id',
        'motto_week_enabled', 'motto_partner_group_id', 'motto_include_trainers', 'motto_order',
    ];

    protected function casts(): array
    {
        return [
            'active'                 => 'boolean',
            'motto_week_enabled'     => 'boolean',
            'motto_include_trainers' => 'boolean',
            'motto_order'            => 'array',
        ];
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

    public function goals()
    {
        return $this->hasMany(TrainingGroupGoal::class, 'training_group_id')->orderBy('sort_order')->orderBy('id');
    }

    public function mottoWeeks()
    {
        return $this->hasMany(GroupMottoWeek::class, 'training_group_id');
    }

    // ── Motto der Woche ──────────────────────────────────────────────────
    //
    // Gruppen, die zusammen trainieren, teilen sich einen Zyklus. Die Gruppe,
    // bei der die Partnergruppe eingetragen ist, fuehrt ihn; die Mitglieder
    // beider Gruppen kommen darin vor. Die Partnergruppe fuehrt keinen
    // eigenen Zyklus - sonst haette dieselbe Person zwei Motto-Wochen.

    public function mottoPartnerGroup()
    {
        return $this->belongsTo(TrainingGroup::class, 'motto_partner_group_id');
    }

    /** Die Gruppe, die diese hier in ihren Zyklus aufgenommen hat (falls vorhanden). */
    public function mottoLedBy()
    {
        return $this->hasOne(TrainingGroup::class, 'motto_partner_group_id')
            ->where('motto_week_enabled', true);
    }

    /**
     * Alle, die im Zyklus dieser Gruppe an der Reihe sind - aus dieser Gruppe
     * und der Partnergruppe, Trainer nur wenn gewuenscht.
     *
     * Sortiert nach der gespeicherten Reihenfolge; wer dort fehlt (neu in der
     * Gruppe), haengt alphabetisch hinten an.
     */
    public function mottoParticipants(): \Illuminate\Support\Collection
    {
        $groupIds = array_values(array_filter([$this->id, $this->motto_partner_group_id]));

        $users = User::query()
            ->where('active', true)
            ->where(function ($q) use ($groupIds) {
                $q->whereHas('trainingGroups', fn($g) => $g->whereIn('training_groups.id', $groupIds));
                if ($this->motto_include_trainers) {
                    $q->orWhereHas('trainerGroups', fn($g) => $g->whereIn('training_groups.id', $groupIds));
                }
            })
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname', 'role']);

        $position = array_flip(array_map('intval', $this->motto_order ?? []));

        return $users
            ->sortBy(fn($u) => sprintf('%06d', $position[$u->id] ?? 999999) . ' ' . $u->lastname . ' ' . $u->firstname)
            ->values();
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
