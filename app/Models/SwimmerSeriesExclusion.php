<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwimmerSeriesExclusion extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = ['user_id', 'recurrence_group_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
