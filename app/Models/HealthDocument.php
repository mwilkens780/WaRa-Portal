<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthDocument extends Model
{
    protected $fillable = [
        'user_id', 'uploaded_by', 'category', 'title',
        'original_filename', 'stored_path', 'file_size', 'tags',
    ];

    protected function casts(): array
    {
        return [
            'tags'      => 'array',
            'file_size' => 'integer',
        ];
    }

    /** Welche Einwilligung im Profil eine Dokumentkategorie abdeckt */
    public const CONSENT_FIELDS = [
        'nutrition'       => 'opt_nutrition',
        'sports_medicine' => 'opt_sports_medicine',
    ];

    /** Kategorien, fuer die die Person aktuell eingewilligt hat */
    public static function consentedCategories(User $user): array
    {
        return array_keys(array_filter(
            self::CONSENT_FIELDS,
            fn($field) => (bool) $user->{$field}
        ));
    }

    /**
     * Besteht die Einwilligung fuer dieses Dokument noch? Nach einem Widerruf
     * darf ausser der betroffenen Person niemand mehr darauf zugreifen.
     */
    public function hasConsent(): bool
    {
        $field = self::CONSENT_FIELDS[$this->category] ?? null;

        return $field !== null && (bool) $this->user?->{$field};
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public static function allTags(): array
    {
        return static::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }
}
