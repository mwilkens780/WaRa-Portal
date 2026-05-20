<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingDiary extends Model
{
    protected $fillable = [
        'training_session_id', 'user_id', 'body', 'mood', 'perceived_intensity',
    ];

    protected function casts(): array
    {
        return [
            'perceived_intensity' => 'integer',
        ];
    }

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getMoodLabelAttribute(): string
    {
        return match($this->mood) {
            'sehr_gut'  => 'Sehr gut',
            'gut'       => 'Gut',
            'mittel'    => 'Mittel',
            'schlecht'  => 'Schlecht',
            'sehr_schlecht' => 'Sehr schlecht',
            default     => '–',
        };
    }

    public function getMoodColorAttribute(): string
    {
        return match($this->mood) {
            'sehr_gut'      => 'text-green-600',
            'gut'           => 'text-green-500',
            'mittel'        => 'text-amber-500',
            'schlecht'      => 'text-orange-500',
            'sehr_schlecht' => 'text-red-600',
            default         => 'text-gray-400',
        };
    }

    public function getMoodEmojiAttribute(): string
    {
        return match($this->mood) {
            'sehr_gut'      => '😄',
            'gut'           => '🙂',
            'mittel'        => '😐',
            'schlecht'      => '😕',
            'sehr_schlecht' => '😞',
            default         => '–',
        };
    }
}
