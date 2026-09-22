<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bewertung eines Leistungskriteriums fuer einen Schwimmer in einer Saison.
 *
 * Je Kriterium, Schwimmer, Saison und Art (self / trainer) genau eine Zeile.
 * Bewertet wird nur noch erreicht (true) / nicht erreicht (false); NULL heisst
 * "noch nicht bewertet". rating und current_value sind Altlasten aus dem
 * frueheren 5-Stufen-System und werden nicht mehr beschrieben.
 */
class TrainingGroupGoalEvaluation extends Model
{
    protected $fillable = [
        'training_group_goal_id', 'user_id', 'season_id', 'evaluator_id',
        'evaluation_type', 'achieved', 'notes', 'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'achieved'     => 'boolean',
            'evaluated_at' => 'date',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(TrainingGroupGoal::class, 'training_group_goal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    /**
     * Legt die Bewertung an oder ueberschreibt sie - immer nur innerhalb der
     * Saison, nie saisonuebergreifend.
     *
     * $achieved kommt aus einem Formular: "1", "0" oder "" (= offen).
     */
    public static function record(
        TrainingGroupGoal $goal,
        int $userId,
        string $type,
        int $seasonId,
        ?string $achieved,
        ?string $notes,
        int $evaluatorId,
    ): self {
        return static::updateOrCreate(
            [
                'training_group_goal_id' => $goal->id,
                'user_id'                => $userId,
                'evaluation_type'        => $type,
                'season_id'              => $seasonId,
            ],
            [
                'achieved'     => $achieved === null || $achieved === '' ? null : $achieved === '1',
                'notes'        => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'evaluator_id' => $evaluatorId,
                'evaluated_at' => today(),
            ]
        );
    }

    public function scopeForSeason(Builder $q, ?int $seasonId): Builder
    {
        return $q->where('season_id', $seasonId);
    }

    /** erreicht / nicht erreicht / offen */
    public function getStatusAttribute(): string
    {
        return match ($this->achieved) {
            true    => 'achieved',
            false   => 'missed',
            default => 'open',
        };
    }
}
