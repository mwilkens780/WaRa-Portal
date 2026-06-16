<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSessionRegistration extends Model
{
    public $timestamps = false;

    protected $fillable = ['training_session_id', 'user_id', 'registered_at'];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime'];
    }

    public function session()
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
