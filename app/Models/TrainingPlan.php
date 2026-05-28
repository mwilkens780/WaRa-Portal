<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingPlan extends Model
{
    protected $fillable = ['training_session_id', 'description', 'attachment_path', 'created_by_id'];

    public function session()   { return $this->belongsTo(TrainingSession::class, 'training_session_id'); }
    public function blocks()    { return $this->hasMany(TrainingPlanBlock::class)->orderBy('sort_order'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by_id'); }
}
