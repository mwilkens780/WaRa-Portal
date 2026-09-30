<?php

namespace App\Console\Commands;

use App\Services\TrainingDataAudit;
use Illuminate\Console\Command;

/**
 * Nur lesend: Befunde zu Trainingsserien und Hallenbelegungen ausgeben.
 * Dieselben Daten zeigt Admin -> Daten -> Datenprüfung Training.
 */
class TrainingAudit extends Command
{
    protected $signature = 'training:audit {--json : Ausgabe als JSON}';

    protected $description = 'Prüft Trainingsserien und Hallenbelegungen auf Datenfehler (ändert nichts)';

    public function handle(TrainingDataAudit $audit): int
    {
        $result = $audit->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        foreach ($result as $block) {
            $this->newLine();
            $this->info($block['title'] . ' – ' . count($block['rows']));
            $this->line('  ' . $block['explain']);
            if ($block['rows']) {
                $this->table(array_keys($block['rows'][0]), array_map(fn($r) => array_map(fn($v) => mb_strimwidth((string) $v, 0, 70, '…'), $r), $block['rows']));
            }
        }

        return self::SUCCESS;
    }
}
