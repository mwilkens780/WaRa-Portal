<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Federation extends Model
{
    public $timestamps = false;

    protected $fillable = ['slug', 'name', 'url'];

    public function competitions()
    {
        return $this->hasMany(Competition::class);
    }
}
