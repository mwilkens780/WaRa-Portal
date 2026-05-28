<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingAttendance extends Model
{
    protected $fillable = [
        'training_session_id', 'user_id', 'attended', 'absence_reason', 'notes',
        'pre_absent', 'pre_absent_note', 'trainer_comment',
    ];

    protected function casts(): array
    {
        return [
            'attended'   => 'boolean',
            'pre_absent' => 'boolean',
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
}
