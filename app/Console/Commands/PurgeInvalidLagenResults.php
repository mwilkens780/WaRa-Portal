<?php

namespace App\Console\Commands;

use App\Models\CompetitionResult;
use Illuminate\Console\Command;

class PurgeInvalidLagenResults extends Command
{
    protected $signature = 'competition:purge-lagen {--delete : Tatsächlich löschen (ohne diesen Flag nur Vorschau)}';
    protected $description = 'Zeigt (oder löscht mit --delete) alle 100L und 200L Ergebnisse, die fehlerhaft vom Crawler eingelesen wurden';

    public function handle(): int
    {
        $query = CompetitionResult::where('discipline', 'L')
            ->whereIn('distance', [100, 200])
            ->with(['competition:id,name,date', 'user:id,firstname,lastname']);

        $results = $query->get();

        if ($results->isEmpty()) {
            $this->info('Keine 100L / 200L Ergebnisse in der Datenbank gefunden.');
            return 0;
        }

        $grouped = $results->groupBy(fn($r) => $r->competition->name . ' (' . $r->competition->date . ')');

        $this->table(
            ['Wettkampf', 'Datum', '100L', '200L'],
            $grouped->map(function ($group, $label) {
                $comp = $group->first()->competition;
                return [
                    $comp->name,
                    $comp->date,
                    $group->where('distance', 100)->count(),
                    $group->where('distance', 200)->count(),
                ];
            })->values()->toArray()
        );

        $this->newLine();
        $this->line(sprintf(
            'Gesamt: <comment>%d</comment> Ergebnisse (%d × 100L, %d × 200L) für <comment>%d</comment> Sportler in <comment>%d</comment> Wettkämpfen',
            $results->count(),
            $results->where('distance', 100)->count(),
            $results->where('distance', 200)->count(),
            $results->pluck('user_id')->unique()->count(),
            $grouped->count()
        ));

        if (! $this->option('delete')) {
            $this->newLine();
            $this->warn('Nur Vorschau. Mit --delete tatsächlich löschen:');
            $this->line('  php artisan competition:purge-lagen --delete');
            return 0;
        }

        if (! $this->confirm('Alle diese Ergebnisse unwiderruflich löschen?')) {
            $this->info('Abgebrochen.');
            return 0;
        }

        $count = CompetitionResult::where('discipline', 'L')
            ->whereIn('distance', [100, 200])
            ->delete();

        $this->info("$count Ergebnisse gelöscht.");
        $this->newLine();
        $this->line('Nächster Schritt: Crawler neu starten, damit die Daten korrekt eingelesen werden.');
        $this->line('  php artisan webclub:crawl   (oder den jeweiligen Crawler-Befehl)');

        return 0;
    }
}
