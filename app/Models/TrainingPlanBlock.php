<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingPlanBlock extends Model
{
    protected $fillable = [
        'training_plan_id', 'sort_order', 'label',
        'repetitions', 'distance', 'disciplines', 'additions', 'materials',
        'comment', 'start_interval_seconds', 'recovery_seconds',
    ];

    protected function casts(): array
    {
        return [
            'disciplines'            => 'array',
            'additions'              => 'array',
            'materials'              => 'array',
            'repetitions'            => 'integer',
            'distance'               => 'integer',
            'sort_order'             => 'integer',
            'start_interval_seconds' => 'integer',
            'recovery_seconds'       => 'integer',
        ];
    }

    public function plan()       { return $this->belongsTo(TrainingPlan::class, 'training_plan_id'); }
    public function blockTimes() { return $this->hasMany(TrainingBlockTime::class); }

    public static array $materialLabels = [
        'Pullbuoy', 'Brett', 'Pullkick', 'Widerstandshose',
        'Fingerpaddles', 'Kurzflossen', 'Gummiband', 'Frontschnorchel',
    ];

    public static array $disciplineLabels = [
        'freistil'      => 'Freistil',
        'ruecken'       => 'Rücken',
        'brust'         => 'Brust',
        'schmetterling' => 'Schmetterling',
        'lagen'         => 'Lagen',
    ];

    public static array $disciplineShort = [
        'freistil'      => 'F',
        'ruecken'       => 'R',
        'brust'         => 'B',
        'schmetterling' => 'S',
        'lagen'         => 'L',
    ];
}
