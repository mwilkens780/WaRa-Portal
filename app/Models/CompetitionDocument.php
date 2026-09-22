<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionDocument extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'competition_id', 'category', 'original_name', 'path', 'size', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'size'       => 'integer',
            'created_at' => 'datetime',
        ];
    }

    const CATEGORIES = [
        // Reihenfolge = Reihenfolge in der Anzeige und im Upload-Formular:
        // so, wie die Dokumente im Ablauf eines Wettkampfs entstehen
        'ausschreibung' => 'Ausschreibung',
        'meldeergebnis' => 'Meldeergebnis',
        'protokoll'     => 'Protokoll',
        'sonstige'      => 'Sonstige',
    ];

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
