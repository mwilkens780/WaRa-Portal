<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSessionSwimmer extends Model
{
    protected $fillable = ['user_id', 'training_session_id', 'recurrence_group_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }
}
