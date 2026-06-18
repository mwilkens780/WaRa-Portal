<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingPlanBlock extends Model
{
    protected $fillable = [
        'training_plan_id', 'sort_order', 'label',
        'repetitions', 'repetitions_nested', 'distance',
        'disciplines', 'additions', 'materials',
        'comment', 'start_interval_seconds', 'recovery_seconds',
    ];

    protected function casts(): array
    {
        return [
            'disciplines'            => 'array',
            'additions'              => 'array',
            'materials'              => 'array',
            'repetitions_nested'     => 'array',
            'repetitions'            => 'integer',
            'distance'               => 'integer',
            'sort_order'             => 'integer',
            'start_interval_seconds' => 'integer',
            'recovery_seconds'       => 'integer',
        ];
    }

    // Total repetitions: product of all nested levels (or plain int for legacy rows)
    public function getTotalRepetitionsAttribute(): int
    {
        $nested = $this->repetitions_nested;
        if (!empty($nested)) {
            return array_reduce($nested, fn($carry, $r) => $carry * max(1, (int)$r), 1);
        }
        return (int)($this->repetitions ?? 0);
    }

    // Display string: "4×6×2" or "4"
    public function getRepetitionsDisplayAttribute(): string
    {
        $nested = $this->repetitions_nested;
        if (!empty($nested)) {
            return implode('×', $nested);
        }
        return (string)($this->repetitions ?? '');
    }

    public function plan()       { return $this->belongsTo(TrainingPlan::class, 'training_plan_id'); }
    public function blockTimes() { return $this->hasMany(TrainingBlockTime::class); }

    public static array $materialLabels = [
        'Pullbuoy', 'Brett', 'Pullkick', 'Widerstandshose',
        'Fingerpaddles', 'Kurzflossen', 'Gummiband', 'Frontschnorchel',
    ];

    public static array $disciplineLabels = [
        'F'  => 'Freistil',
        'R'  => 'Rücken',
        'B'  => 'Brust',
        'S'  => 'Schmetterling',
        'L'  => 'Lagen',
        'HL' => 'Hauptlage',
        'NL' => 'Nebenlage',
    ];

    public static array $disciplineShort = [
        'F'  => 'F',
        'R'  => 'R',
        'B'  => 'B',
        'S'  => 'S',
        'L'  => 'L',
        'HL' => 'HL',
        'NL' => 'NL',
    ];
}
