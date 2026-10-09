<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Einmalige Bereinigung (Freigabe Martin, 09.10.2026): Startschwimmer-Zeiten aus
 * Mixed-Staffeln zählen nach DSV-Vorgabe nicht als offizielle Einzelzeit. Seit
 * heute legt der Import sie nicht mehr an; hier fallen die bereits importierten weg.
 *
 * Erkennung: Gemischte Staffeln speichern kein Geschlecht (NULL). Gelöscht wird
 * ein Startschwimmer-Ergebnis (relay_leadoff), wenn derselbe Sportler im selben
 * Wettkampf Startschwimmer (Abschnitt 1) einer solchen Staffel mit gleicher
 * Strecke und passender Lage war (Lagenstaffel: Rücken).
 *
 * Sicherung: alle gelöschten Zeilen vollständig in
 * storage/app/cleanup/mixed-relay-leadoffs-<Zeit>.json; down() stellt sie wieder her.
 */
return new class extends Migration
{
    private const DIR = 'cleanup';

    public function up(): void
    {
        $ids = DB::table('competition_results as cr')
            ->join('relay_results as rr', function ($j) {
                $j->on('rr.competition_id', '=', 'cr.competition_id')->on('rr.distance', '=', 'cr.distance');
            })
            ->join('relay_members as rm', function ($j) {
                $j->on('rm.relay_result_id', '=', 'rr.id')->where('rm.leg', 1);
            })
            ->join('athletes as a', function ($j) {
                $j->on('a.id', '=', 'rm.athlete_id')->on('a.user_id', '=', 'cr.user_id');
            })
            ->where('cr.relay_leadoff', true)
            ->where(fn($q) => $q->whereNull('rr.gender')->orWhere('rr.gender', 'X'))
            ->whereRaw("cr.discipline = CASE WHEN rr.discipline IN ('L', 'Lagen') THEN 'R' ELSE rr.discipline END")
            ->distinct()->pluck('cr.id')->all();

        if (!$ids) {
            Log::info('Mixed-Staffel-Startschwimmer: nichts zu bereinigen.');
            return;
        }

        $rows    = DB::table('competition_results')->whereIn('id', $ids)->get();
        $records = DB::table('records')->whereIn('competition_result_id', $ids)->get(['id', 'type', 'discipline', 'distance', 'competition_result_id']);

        $file = self::DIR . '/mixed-relay-leadoffs-' . now()->format('Ymd-His') . '.json';
        Storage::disk('local')->put($file, json_encode([
            'reason'   => 'Startschwimmer-Zeiten aus Mixed-Staffeln (DSV-Vorgabe), Freigabe Martin 09.10.2026',
            'results'  => $rows,
            'records'  => $records,   // Rekorde, die auf eine dieser Zeiten verwiesen (Verweis wird NULL)
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        DB::table('competition_results')->whereIn('id', $ids)->delete();

        Log::warning('Mixed-Staffel-Startschwimmer bereinigt', [
            'geloescht'     => count($ids),
            'wettkaempfe'   => $rows->pluck('competition_id')->unique()->values()->all(),
            'rekorde'       => $records->pluck('id')->all(),
            'sicherung'     => $file,
        ]);
    }

    public function down(): void
    {
        $files = collect(Storage::disk('local')->files(self::DIR))
            ->filter(fn($f) => str_contains($f, 'mixed-relay-leadoffs-'))->sort()->values();
        if ($files->isEmpty()) return;

        $data = json_decode(Storage::disk('local')->get($files->last()), true);
        foreach ($data['results'] ?? [] as $row) {
            if (!DB::table('competition_results')->where('id', $row['id'])->exists()) {
                DB::table('competition_results')->insert($row);
            }
        }
        foreach ($data['records'] ?? [] as $rec) {
            DB::table('records')->where('id', $rec['id'])->whereNull('competition_result_id')
                ->update(['competition_result_id' => $rec['competition_result_id']]);
        }
    }
};
