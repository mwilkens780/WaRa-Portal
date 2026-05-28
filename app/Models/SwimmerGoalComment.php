<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwimmerGoalComment extends Model
{
    protected $fillable = ['swimmer_goal_id', 'trainer_id', 'comment'];

    public function goal()    { return $this->belongsTo(SwimmerGoal::class, 'swimmer_goal_id'); }
    public function trainer() { return $this->belongsTo(User::class, 'trainer_id'); }
}
