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

    public static array $typeLabels = [
        'quantitative' => 'Messbar',
        'qualitative'  => 'Qualitativ',
    ];

    public static array $ratingLabels = [
        1 => 'Nicht begonnen',
        2 => 'Erste Schritte',
        3 => 'In Arbeit',
        4 => 'Gut auf dem Weg',
        5 => 'Erreicht',
    ];

    public static array $ratingColors = [
        1 => 'text-gray-500',
        2 => 'text-orange-500',
        3 => 'text-yellow-500',
        4 => 'text-blue-500',
        5 => 'text-green-600',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class, 'training_group_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(TrainingGroupGoalEvaluation::class, 'training_group_goal_id');
    }

    public function selfEvaluationFor(int $userId): ?TrainingGroupGoalEvaluation
    {
        return $this->evaluations
            ->where('user_id', $userId)
            ->where('evaluation_type', 'self')
            ->first();
    }

    public function trainerEvaluationFor(int $userId): ?TrainingGroupGoalEvaluation
    {
        return $this->evaluations
            ->where('user_id', $userId)
            ->where('evaluation_type', 'trainer')
            ->first();
    }
}
