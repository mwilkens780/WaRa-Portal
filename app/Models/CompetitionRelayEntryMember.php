<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionRelayEntryMember extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = ['relay_entry_id', 'user_id', 'position'];

    public function relayEntry()
    {
        return $this->belongsTo(CompetitionRelayEntry::class, 'relay_entry_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
