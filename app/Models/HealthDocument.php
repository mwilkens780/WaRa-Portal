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
