<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CompetitionWebclubImportController extends Controller
{
    public function showForm()
    {
        $seasons = Season::orderByDesc('start_date')->get();
        return view('admin.competitions.webclub-import', compact('seasons'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:2048'],
        ]);

        $content = file_get_contents($request->file('csv_file')->getRealPath());

        // Strip UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Convert from Windows-1252 if not valid UTF-8 (common in German software exports)
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

        if (count($lines) < 2) {
            return back()->withErrors(['csv_file' => 'Die Datei enthält zu wenig Zeilen.']);
        }

        // Row 1: "Veranstaltungen in der Saison 2025/2026;" → extract "2025/2026"
        $headerLine   = trim($lines[0], " \t;");
        $csvSeasonName = null;
        if (preg_match('/(\d{4}\/\d{4}|\d{4}\/\d{2})/', $headerLine, $m)) {
            $csvSeasonName = $m[1];
        }

        // Match season from DB
        $seasons = Season::orderByDesc('start_date')->get();
        $suggestedSeason = null;
        if ($csvSeasonName) {
            // Normalize short form "2025/26" → "2025/2026" for comparison
            $normalized = preg_replace('/(\d{4})\/(\d{2})$/', '$1/20$2', $csvSeasonName);
            $suggestedSeason = $seasons->first(function ($s) use ($csvSeasonName, $normalized) {
                return $s->name === $csvSeasonName
                    || $s->name === $normalized
                    || str_contains($s->name, $csvSeasonName);
            });
        }
        $suggestedSeason ??= Season::current() ?? $seasons->first();

        // Skip row 1 (header label) and row 2 (column headers), parse data rows from row 3
        $rows = [];
        for ($i = 2; $i < count($lines); $i++) {
            $cols = str_getcsv($lines[$i], ';');
            // Pad to at least 5 columns
            while (count($cols) < 5) $cols[] = '';

            $dateRaw   = trim($cols[0]);
            $name      = trim($cols[1]);
            $meldeStr  = trim($cols[2]);

            if (empty($name) || empty($dateRaw)) continue;

            // Handle date ranges: "dd.mm.yyyy - dd.mm.yyyy"
            $dateEndStr = null;
            $dateStr    = $dateRaw;
            if (str_contains($dateRaw, ' - ')) {
                [$dateStr, $dateEndStr] = array_map('trim', explode(' - ', $dateRaw, 2));
            }

            $date = $this->parseGermanDate($dateStr);
            if (!$date) continue;

            $dateEnd      = $dateEndStr ? $this->parseGermanDate($dateEndStr) : null;
            $meldeschluss = $this->parseGermanDate($meldeStr);

            $exists = Competition::where('name', $name)
                ->whereDate('date', $date->format('Y-m-d'))
                ->exists();

            $rows[] = [
                'name'         => $name,
                'date'         => $date->format('Y-m-d'),
                'date_end'     => $dateEnd?->format('Y-m-d'),
                'date_disp'    => $dateEnd
                    ? $date->format('d.m.Y') . ' – ' . $dateEnd->format('d.m.Y')
                    : $date->format('d.m.Y'),
                'meldeschluss' => $meldeschluss?->format('Y-m-d'),
                'melde_disp'   => $meldeschluss?->format('d.m.Y') ?? '–',
                'location'     => '',
                'type'         => 'regional',
                'exists'       => $exists,
            ];
        }

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'Keine gültigen Termine in der Datei gefunden. Bitte Dateiformat prüfen.']);
        }

        return view('admin.competitions.webclub-import', compact(
            'seasons', 'rows', 'suggestedSeason', 'csvSeasonName', 'headerLine'
        ));
    }

    public function import(Request $request)
    {
        $request->validate([
            'season_id' => ['nullable', 'exists:seasons,id'],
            'rows'      => ['required', 'array', 'min:1'],
        ]);

        $seasonId = $request->integer('season_id') ?: null;
        $created  = 0;
        $skipped  = 0;

        foreach ($request->input('rows', []) as $row) {
            if (empty($row['selected'])) continue;

            $name = trim($row['name'] ?? '');
            $date = $row['date'] ?? null;
            if (!$name || !$date) continue;

            // Skip duplicates silently
            if (Competition::where('name', $name)->whereDate('date', $date)->exists()) {
                $skipped++;
                continue;
            }

            Competition::create([
                'name'         => $name,
                'date'         => $date,
                'date_end'     => $row['date_end'] ?: null,
                'meldeschluss' => $row['meldeschluss'] ?: null,
                'type'         => $row['type'] ?? 'regional',
                'location'     => trim($row['location'] ?? ''),
                'organizer'    => null,
                'course'       => null,
                'description'  => null,
                'season_id'    => $seasonId,
            ]);
            $created++;
        }

        $msg = "{$created} Wettkampf-Termin" . ($created !== 1 ? 'e' : '') . " importiert.";
        if ($skipped) {
            $msg .= " {$skipped} bereits vorhandene übersprungen.";
        }

        return redirect()->route('admin.competitions.index')->with('success', $msg);
    }

    private function parseGermanDate(string $str): ?Carbon
    {
        $str = trim($str);
        if (empty($str)) return null;
        // dd.mm.yyyy HH:MM (Meldeschluss with time)
        try { return Carbon::createFromFormat('d.m.Y H:i', $str)->startOfDay(); } catch (\Exception $e) {}
        // dd.mm.yyyy
        try { return Carbon::createFromFormat('d.m.Y', $str); } catch (\Exception $e) {}
        // d.m.yyyy
        try { return Carbon::createFromFormat('j.n.Y', $str); } catch (\Exception $e) {}
        // dd.mm.yy
        try { return Carbon::createFromFormat('d.m.y', $str); } catch (\Exception $e) {}
        // ISO fallback
        try { return Carbon::parse($str); } catch (\Exception $e) {}
        return null;
    }
}
