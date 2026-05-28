<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Provides public holiday and school vacation data for SH and HH.
 *
 * Public holidays are calculated dynamically via the Gaussian Easter algorithm.
 * School vacation dates are maintained here as static data.
 * Verify/update school vacation dates at:
 *   https://www.schulferien.org/schleswig-holstein/ferien/
 *   https://www.schulferien.org/hamburg/ferien/
 */
class HolidayService
{
    /**
     * Build a date-keyed map for the given range.
     * Each entry: [
     *   'holiday'     => string|null,  // public holiday name (SH + HH identical)
     *   'vacation_sh' => string|null,  // SH school vacation name
     *   'vacation_hh' => string|null,  // HH school vacation name
     * ]
     */
    public static function buildMap(Carbon $from, Carbon $to): array
    {
        $map    = [];
        $cursor = $from->copy()->startOfDay();
        $end    = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $map[$cursor->format('Y-m-d')] = [
                'holiday'     => null,
                'vacation_sh' => null,
                'vacation_hh' => null,
            ];
            $cursor->addDay();
        }

        foreach (range($from->year, $to->year) as $year) {
            foreach (self::publicHolidays($year) as $date => $name) {
                if (isset($map[$date])) {
                    $map[$date]['holiday'] = $name;
                }
            }
        }

        foreach (self::schoolVacations() as $v) {
            $vStart = Carbon::parse($v['start'])->startOfDay();
            $vEnd   = Carbon::parse($v['end'])->startOfDay();
            $start  = $vStart->gt($from) ? $vStart : $from->copy()->startOfDay();
            $finish = $vEnd->lt($end)    ? $vEnd   : $end;
            if ($start->gt($finish)) continue;
            $c = $start->copy();
            while ($c->lte($finish)) {
                $key = $c->format('Y-m-d');
                if (isset($map[$key])) {
                    if (in_array($v['state'], ['sh', 'both'])) {
                        $map[$key]['vacation_sh'] = $v['name'];
                    }
                    if (in_array($v['state'], ['hh', 'both'])) {
                        $map[$key]['vacation_hh'] = $v['name'];
                    }
                }
                $c->addDay();
            }
        }

        return $map;
    }

    /** Public holidays for SH + HH (both states share identical dates). */
    private static function publicHolidays(int $year): array
    {
        $easter = self::easter($year);
        return [
            "$year-01-01"                                   => 'Neujahr',
            $easter->copy()->subDays(2)->format('Y-m-d')    => 'Karfreitag',
            $easter->copy()->addDay()->format('Y-m-d')      => 'Ostermontag',
            "$year-05-01"                                   => 'Tag der Arbeit',
            $easter->copy()->addDays(39)->format('Y-m-d')   => 'Christi Himmelfahrt',
            $easter->copy()->addDays(50)->format('Y-m-d')   => 'Pfingstmontag',
            "$year-10-03"                                   => 'Tag der deutschen Einheit',
            "$year-10-31"                                   => 'Reformationstag',
            "$year-12-25"                                   => '1. Weihnachtstag',
            "$year-12-26"                                   => '2. Weihnachtstag',
        ];
    }

    /** Easter Sunday via the Gaussian algorithm. */
    private static function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;
        return Carbon::create($year, $month, $day);
    }

    private static function schoolVacations(): array
    {
        return [
            // ══ 2024 SH ═══════════════════════════════════════════════════
            ['name' => 'Winterferien',       'start' => '2024-02-12', 'end' => '2024-02-16', 'state' => 'sh'],
            ['name' => 'Osterferien',        'start' => '2024-03-22', 'end' => '2024-04-05', 'state' => 'sh'],
            ['name' => 'Sommerferien',       'start' => '2024-07-22', 'end' => '2024-08-30', 'state' => 'sh'],
            ['name' => 'Herbstferien',       'start' => '2024-10-14', 'end' => '2024-10-25', 'state' => 'sh'],
            ['name' => 'Weihnachtsferien',   'start' => '2024-12-23', 'end' => '2025-01-06', 'state' => 'sh'],

            // ══ 2024 HH ═══════════════════════════════════════════════════
            ['name' => 'Winterferien',       'start' => '2024-01-29', 'end' => '2024-02-02', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',    'start' => '2024-03-15', 'end' => '2024-03-28', 'state' => 'hh'],
            ['name' => 'Sommerferien',       'start' => '2024-07-18', 'end' => '2024-08-28', 'state' => 'hh'],
            ['name' => 'Herbstferien',       'start' => '2024-10-04', 'end' => '2024-10-18', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',   'start' => '2024-12-20', 'end' => '2025-01-03', 'state' => 'hh'],

            // ══ 2025 SH ═══════════════════════════════════════════════════
            ['name' => 'Winterferien',       'start' => '2025-02-17', 'end' => '2025-02-21', 'state' => 'sh'],
            ['name' => 'Osterferien',        'start' => '2025-04-07', 'end' => '2025-04-22', 'state' => 'sh'],
            ['name' => 'Sommerferien',       'start' => '2025-07-07', 'end' => '2025-08-15', 'state' => 'sh'],
            ['name' => 'Herbstferien',       'start' => '2025-10-13', 'end' => '2025-10-24', 'state' => 'sh'],
            ['name' => 'Weihnachtsferien',   'start' => '2025-12-22', 'end' => '2026-01-05', 'state' => 'sh'],

            // ══ 2025 HH ═══════════════════════════════════════════════════
            ['name' => 'Winterferien',       'start' => '2025-02-03', 'end' => '2025-02-07', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',    'start' => '2025-03-14', 'end' => '2025-03-21', 'state' => 'hh'],
            ['name' => 'Sommerferien',       'start' => '2025-07-17', 'end' => '2025-08-27', 'state' => 'hh'],
            ['name' => 'Herbstferien',       'start' => '2025-10-06', 'end' => '2025-10-17', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',   'start' => '2025-12-22', 'end' => '2026-01-02', 'state' => 'hh'],

            // ══ 2026 SH ═══════════════════════════════════════════════════
            ['name' => 'Winterferien',       'start' => '2026-02-02', 'end' => '2026-02-06', 'state' => 'sh'],
            ['name' => 'Osterferien',        'start' => '2026-03-27', 'end' => '2026-04-10', 'state' => 'sh'],
            ['name' => 'Sommerferien',       'start' => '2026-07-06', 'end' => '2026-08-14', 'state' => 'sh'],
            ['name' => 'Herbstferien',       'start' => '2026-10-12', 'end' => '2026-10-23', 'state' => 'sh'],
            ['name' => 'Weihnachtsferien',   'start' => '2026-12-23', 'end' => '2027-01-06', 'state' => 'sh'],

            // ══ 2026 HH ═══════════════════════════════════════════════════
            ['name' => 'Winterferien',       'start' => '2026-01-26', 'end' => '2026-01-30', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',    'start' => '2026-03-16', 'end' => '2026-03-27', 'state' => 'hh'],
            ['name' => 'Sommerferien',       'start' => '2026-07-16', 'end' => '2026-08-26', 'state' => 'hh'],
            ['name' => 'Herbstferien',       'start' => '2026-10-05', 'end' => '2026-10-16', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',   'start' => '2026-12-23', 'end' => '2027-01-01', 'state' => 'hh'],

            // ══ 2027 SH ═══════════════════════════════════════════════════
            ['name' => 'Winterferien',       'start' => '2027-02-15', 'end' => '2027-02-19', 'state' => 'sh'],
            ['name' => 'Osterferien',        'start' => '2027-03-22', 'end' => '2027-04-02', 'state' => 'sh'],
            ['name' => 'Sommerferien',       'start' => '2027-07-05', 'end' => '2027-08-13', 'state' => 'sh'],
            ['name' => 'Herbstferien',       'start' => '2027-10-11', 'end' => '2027-10-22', 'state' => 'sh'],
            ['name' => 'Weihnachtsferien',   'start' => '2027-12-23', 'end' => '2028-01-06', 'state' => 'sh'],

            // ══ 2027 HH ═══════════════════════════════════════════════════
            ['name' => 'Winterferien',       'start' => '2027-02-01', 'end' => '2027-02-05', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',    'start' => '2027-03-15', 'end' => '2027-03-26', 'state' => 'hh'],
            ['name' => 'Sommerferien',       'start' => '2027-07-15', 'end' => '2027-08-25', 'state' => 'hh'],
            ['name' => 'Herbstferien',       'start' => '2027-10-04', 'end' => '2027-10-15', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',   'start' => '2027-12-23', 'end' => '2028-01-01', 'state' => 'hh'],
        ];
    }
}
