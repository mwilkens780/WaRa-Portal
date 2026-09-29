<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Provides public holiday and school vacation data for SH and HH.
 *
 * Public holidays are calculated dynamically via the Gaussian Easter algorithm.
 * School vacation dates are maintained here as static data from the official
 * state sources (see schoolVacations()).
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

    /**
     * Schulferien, erster und letzter Ferientag (Festland; Inseln/Halligen in
     * SH haben teils abweichende Sommer-/Herbstferien und fehlen hier).
     *
     * Quellen (geprueft 29.09.2026):
     *   SH: https://www.schleswig-holstein.de/DE/landesregierung/themen/bildung-hochschulen/ferientermine
     *       SH hat keine Winterferien, nur landesweit festgelegte bewegliche Ferientage.
     *   HH: Ferienordnung 2024/25 bis 2029/30, https://www.hamburg.de/resource/blob/134372/5bc131bdd36a604f67b361d21f7df37e/ferienordnung-hamburg-2024-2030-data.pdf
     * Gegengeprueft mit openholidaysapi.org und schulferien.org.
     * Sommerferien SH 2028 ff. sind noch nicht veroeffentlicht.
     */
    private static function schoolVacations(): array
    {
        return [
            // ══ Schleswig-Holstein ═════════════════════════════════════════
            ['name' => 'Weihnachtsferien',        'start' => '2023-12-27', 'end' => '2024-01-06', 'state' => 'sh'],
            ['name' => 'Osterferien',             'start' => '2024-04-02', 'end' => '2024-04-19', 'state' => 'sh'],
            ['name' => 'Brückentag Himmelfahrt',  'start' => '2024-05-10', 'end' => '2024-05-11', 'state' => 'sh'],
            ['name' => 'Sommerferien',            'start' => '2024-07-22', 'end' => '2024-08-31', 'state' => 'sh'],
            ['name' => 'Herbstferien',            'start' => '2024-10-21', 'end' => '2024-11-01', 'state' => 'sh'],
            ['name' => 'Weihnachtsferien',        'start' => '2024-12-19', 'end' => '2025-01-07', 'state' => 'sh'],
            ['name' => 'Osterferien',             'start' => '2025-04-11', 'end' => '2025-04-25', 'state' => 'sh'],
            ['name' => 'Brückentag Himmelfahrt',  'start' => '2025-05-30', 'end' => '2025-05-30', 'state' => 'sh'],
            ['name' => 'Sommerferien',            'start' => '2025-07-28', 'end' => '2025-09-06', 'state' => 'sh'],
            ['name' => 'Herbstferien',            'start' => '2025-10-20', 'end' => '2025-10-30', 'state' => 'sh'],
            ['name' => 'Beweglicher Ferientag',   'start' => '2025-11-28', 'end' => '2025-11-28', 'state' => 'sh'],
            ['name' => 'Weihnachtsferien',        'start' => '2025-12-19', 'end' => '2026-01-06', 'state' => 'sh'],
            ['name' => 'Bewegliche Ferientage',   'start' => '2026-02-02', 'end' => '2026-02-03', 'state' => 'sh'],
            ['name' => 'Osterferien',             'start' => '2026-03-26', 'end' => '2026-04-10', 'state' => 'sh'],
            ['name' => 'Brückentag Himmelfahrt',  'start' => '2026-05-15', 'end' => '2026-05-15', 'state' => 'sh'],
            ['name' => 'Sommerferien',            'start' => '2026-07-04', 'end' => '2026-08-15', 'state' => 'sh'],
            ['name' => 'Herbstferien',            'start' => '2026-10-12', 'end' => '2026-10-24', 'state' => 'sh'],
            ['name' => 'Weihnachtsferien',        'start' => '2026-12-21', 'end' => '2027-01-06', 'state' => 'sh'],
            ['name' => 'Bewegliche Ferientage',   'start' => '2027-02-01', 'end' => '2027-02-02', 'state' => 'sh'],
            ['name' => 'Osterferien',             'start' => '2027-03-30', 'end' => '2027-04-10', 'state' => 'sh'],
            ['name' => 'Brückentag Himmelfahrt',  'start' => '2027-05-07', 'end' => '2027-05-07', 'state' => 'sh'],
            ['name' => 'Sommerferien',            'start' => '2027-07-03', 'end' => '2027-08-14', 'state' => 'sh'],
            ['name' => 'Herbstferien',            'start' => '2027-10-11', 'end' => '2027-10-23', 'state' => 'sh'],
            ['name' => 'Weihnachtsferien',        'start' => '2027-12-23', 'end' => '2028-01-08', 'state' => 'sh'],
            ['name' => 'Beweglicher Ferientag',   'start' => '2028-01-31', 'end' => '2028-01-31', 'state' => 'sh'],
            ['name' => 'Osterferien',             'start' => '2028-04-03', 'end' => '2028-04-15', 'state' => 'sh'],
            ['name' => 'Brückentag Himmelfahrt',  'start' => '2028-05-26', 'end' => '2028-05-26', 'state' => 'sh'],

            // ══ Hamburg ════════════════════════════════════════════════════
            ['name' => 'Weihnachtsferien',        'start' => '2023-12-22', 'end' => '2024-01-05', 'state' => 'hh'],
            ['name' => 'Halbjahrespause',         'start' => '2024-02-02', 'end' => '2024-02-02', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',         'start' => '2024-03-18', 'end' => '2024-03-28', 'state' => 'hh'],
            ['name' => 'Brückentag',              'start' => '2024-05-10', 'end' => '2024-05-10', 'state' => 'hh'],
            ['name' => 'Himmelfahrt/Pfingsten',   'start' => '2024-05-21', 'end' => '2024-05-24', 'state' => 'hh'],
            ['name' => 'Sommerferien',            'start' => '2024-07-18', 'end' => '2024-08-28', 'state' => 'hh'],
            ['name' => 'Brückentag',              'start' => '2024-10-04', 'end' => '2024-10-04', 'state' => 'hh'],
            ['name' => 'Herbstferien',            'start' => '2024-10-21', 'end' => '2024-11-01', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',        'start' => '2024-12-20', 'end' => '2025-01-03', 'state' => 'hh'],
            ['name' => 'Halbjahrespause',         'start' => '2025-01-31', 'end' => '2025-01-31', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',         'start' => '2025-03-10', 'end' => '2025-03-21', 'state' => 'hh'],
            ['name' => 'Brückentag',              'start' => '2025-05-02', 'end' => '2025-05-02', 'state' => 'hh'],
            ['name' => 'Himmelfahrt/Pfingsten',   'start' => '2025-05-26', 'end' => '2025-05-30', 'state' => 'hh'],
            ['name' => 'Sommerferien',            'start' => '2025-07-24', 'end' => '2025-09-03', 'state' => 'hh'],
            ['name' => 'Herbstferien',            'start' => '2025-10-20', 'end' => '2025-10-31', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',        'start' => '2025-12-17', 'end' => '2026-01-02', 'state' => 'hh'],
            ['name' => 'Halbjahrespause',         'start' => '2026-01-30', 'end' => '2026-01-30', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',         'start' => '2026-03-02', 'end' => '2026-03-13', 'state' => 'hh'],
            ['name' => 'Himmelfahrt/Pfingsten',   'start' => '2026-05-11', 'end' => '2026-05-15', 'state' => 'hh'],
            ['name' => 'Sommerferien',            'start' => '2026-07-09', 'end' => '2026-08-19', 'state' => 'hh'],
            ['name' => 'Herbstferien',            'start' => '2026-10-19', 'end' => '2026-10-30', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',        'start' => '2026-12-21', 'end' => '2027-01-01', 'state' => 'hh'],
            ['name' => 'Halbjahrespause',         'start' => '2027-01-29', 'end' => '2027-01-29', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',         'start' => '2027-03-01', 'end' => '2027-03-12', 'state' => 'hh'],
            ['name' => 'Himmelfahrt/Pfingsten',   'start' => '2027-05-07', 'end' => '2027-05-14', 'state' => 'hh'],
            ['name' => 'Sommerferien',            'start' => '2027-07-01', 'end' => '2027-08-11', 'state' => 'hh'],
            ['name' => 'Herbstferien',            'start' => '2027-10-11', 'end' => '2027-10-22', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',        'start' => '2027-12-20', 'end' => '2027-12-31', 'state' => 'hh'],
            ['name' => 'Halbjahrespause',         'start' => '2028-01-28', 'end' => '2028-01-28', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',         'start' => '2028-03-06', 'end' => '2028-03-17', 'state' => 'hh'],
            ['name' => 'Himmelfahrt/Pfingsten',   'start' => '2028-05-22', 'end' => '2028-05-26', 'state' => 'hh'],
            ['name' => 'Sommerferien',            'start' => '2028-07-03', 'end' => '2028-08-11', 'state' => 'hh'],
            ['name' => 'Herbstferien',            'start' => '2028-10-02', 'end' => '2028-10-13', 'state' => 'hh'],
            ['name' => 'Brückentag',              'start' => '2028-10-30', 'end' => '2028-10-30', 'state' => 'hh'],
            ['name' => 'Weihnachtsferien',        'start' => '2028-12-18', 'end' => '2028-12-29', 'state' => 'hh'],
            ['name' => 'Halbjahrespause',         'start' => '2029-02-02', 'end' => '2029-02-02', 'state' => 'hh'],
            ['name' => 'Frühjahrsferien',         'start' => '2029-03-05', 'end' => '2029-03-16', 'state' => 'hh'],
            ['name' => 'Himmelfahrt/Pfingsten',   'start' => '2029-05-11', 'end' => '2029-05-18', 'state' => 'hh'],
            ['name' => 'Sommerferien',            'start' => '2029-07-02', 'end' => '2029-08-10', 'state' => 'hh'],
        ];
    }
}
