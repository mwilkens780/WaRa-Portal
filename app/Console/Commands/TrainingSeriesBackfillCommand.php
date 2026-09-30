<?php

namespace App\Console\Commands;

use App\Services\TrainingSeriesBackfill;
use Illuminate\Console\Command;

/**
 * Bestand in Trainingsserien uebernehmen. Ohne --apply nur Probelauf.
 * Dasselbe bietet Admin -> Daten -> Datenprüfung Training.
 */
class TrainingSeriesBackfillCommand extends Command
{
    protected $signature = 'training:series-backfill {--apply : Wirklich speichern (sonst nur Probelauf)}';

    protected $description = 'Legt Trainingsserien aus den vorhandenen Einheiten an und verbindet die Hallenbelegungen';

    public function handle(TrainingSeriesBackfill $backfill): int
    {
        $result = $backfill->run((bool) $this->option('apply'));

        if ($result['rows']) {
            $this->table(array_keys($result['rows'][0]), array_map(fn($r) => array_map(fn($v) => mb_strimwidth((string) $v, 0, 50, '…'), $r), $result['rows']));
        }
        $t = $result['totals'];
        $this->info("{$t['serien']} Serien neu, {$t['vorhanden']} schon vorhanden, {$t['abweichungen']} Einheiten mit Abweichung, "
            . "{$t['belegungen']} Belegungen zugeordnet, {$t['verbunden']} verbunden, {$t['entfernt']} doppelte entfernt.");
        $result['applied']
            ? $this->info('Gespeichert.')
            : $this->warn('PROBELAUF – nichts gespeichert. Zum Ausführen: --apply');

        return self::SUCCESS;
    }
}
