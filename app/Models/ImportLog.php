<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    public $timestamps = false;
    protected $table = 'import_log';

    protected $fillable = [
        'source', 'source_url', 'filename', 'status',
        'competition_id', 'message', 'imported_at',
    ];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function isSuccess(): bool { return $this->status === 'success'; }
    public function isSkipped(): bool { return $this->status === 'skipped'; }
    public function isError(): bool   { return $this->status === 'error'; }
}
