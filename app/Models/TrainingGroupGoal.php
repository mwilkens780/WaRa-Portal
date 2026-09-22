<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingGroupGoal extends Model
{
    protected $fillable = [
        'training_group_id', 'title', 'description', 'type', 'target_value', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    // Fachlich: Leistungskriterium der Trainingsgruppe. Die Erfuellung wird je
    // Saison und Schwimmer mit erreicht / nicht erreicht bewertet und entscheidet
    // ueber Verbleib oder Wechsel der Gruppe.

    public static array $typeLabels = [
        'quantitative' => 'Messbar',
        'qualitative'  => 'Qualitativ',
    ];

    public static array $statusLabels = [
        'achieved' => 'Erreicht',
        'missed'   => 'Nicht erreicht',
        'open'     => 'Offen',
    ];

    public static array $statusBadges = [
        'achieved' => 'bg-green-100 text-green-700',
        'missed'   => 'bg-red-100 text-red-700',
        'open'     => 'bg-gray-100 text-gray-500',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'training_group_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(TrainingGroupGoalEvaluation::class, 'training_group_goal_id');
    }

    // Beide Helfer arbeiten auf den geladenen Bewertungen. Die Aufrufer laden
    // nur eine Saison; die Saison-ID hier ist die zweite Sicherung dagegen,
    // dass eine Vorjahresbewertung als aktuelle durchrutscht.

    public function selfEvaluationFor(int $userId, ?int $seasonId = null): ?TrainingGroupGoalEvaluation
    {
        return $this->evaluationFor($userId, 'self', $seasonId);
    }

    public function trainerEvaluationFor(int $userId, ?int $seasonId = null): ?TrainingGroupGoalEvaluation
    {
        return $this->evaluationFor($userId, 'trainer', $seasonId);
    }

    private function evaluationFor(int $userId, string $type, ?int $seasonId): ?TrainingGroupGoalEvaluation
    {
        return $this->evaluations
            ->where('user_id', $userId)
            ->where('evaluation_type', $type)
            ->when($seasonId !== null, fn($c) => $c->where('season_id', $seasonId))
            ->first();
    }
}
