<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingBlockTime extends Model
{
    protected $fillable = ['training_plan_block_id', 'user_id', 'repetition', 'time_cs'];

    protected function casts(): array
    {
        return [
            'repetition' => 'integer',
            'time_cs'    => 'integer',
        ];
    }

    public function block() { return $this->belongsTo(TrainingPlanBlock::class, 'training_plan_block_id'); }
    public function user()  { return $this->belongsTo(User::class); }

    public static function format(?int $cs): string
    {
        if (!$cs) return '';
        $min  = intdiv($cs, 6000);
        $sec  = intdiv($cs % 6000, 100);
        $hund = $cs % 100;
        return sprintf('%d:%02d,%02d', $min, $sec, $hund);
    }

    public static function parseCs(?string $value): ?int
    {
        if (!$value || trim($value) === '' || trim($value) === '–') return null;
        $v = str_replace('.', ',', trim($value));
        // m:ss,cc
        if (preg_match('/^(\d+):(\d{1,2})[,](\d{1,2})$/', $v, $m)) {
            return (int)$m[1] * 6000 + (int)$m[2] * 100 + (int)str_pad($m[3], 2, '0');
        }
        // m:ss
        if (preg_match('/^(\d+):(\d{1,2})$/', $v, $m)) {
            return (int)$m[1] * 6000 + (int)$m[2] * 100;
        }
        // ss,cc
        if (preg_match('/^(\d{1,2})[,](\d{1,2})$/', $v, $m)) {
            return (int)$m[1] * 100 + (int)str_pad($m[2], 2, '0');
        }
        return null;
    }
}
