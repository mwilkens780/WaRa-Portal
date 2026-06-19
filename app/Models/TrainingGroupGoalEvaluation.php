<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingGroupGoalEvaluation extends Model
{
    protected $fillable = [
        'training_group_goal_id', 'user_id', 'evaluator_id',
        'evaluation_type', 'rating', 'current_value', 'notes', 'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'rating'       => 'integer',
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

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function getRatingLabelAttribute(): ?string
    {
        return $this->rating ? (TrainingGroupGoal::$ratingLabels[$this->rating] ?? null) : null;
    }

    public function getRatingColorAttribute(): string
    {
        return TrainingGroupGoal::$ratingColors[$this->rating ?? 0] ?? 'text-gray-400';
    }
}
