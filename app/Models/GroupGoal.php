<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupGoal extends Model
{
    protected $fillable = [
        'training_group_id', 'season_id', 'created_by_id',
        'title', 'description', 'target_count', 'achieved_count', 'achieved',
    ];

    protected function casts(): array
    {
        return [
            'achieved'       => 'boolean',
            'target_count'   => 'integer',
            'achieved_count' => 'integer',
        ];
    }

    public function trainingGroup() { return $this->belongsTo(TrainingGroup::class); }
    public function season()        { return $this->belongsTo(Season::class); }
    public function createdBy()     { return $this->belongsTo(User::class, 'created_by_id'); }
}
