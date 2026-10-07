<?php

namespace App\Services\Import;

use App\Models\RelayResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Speichert ein Staffelergebnis aus einer DSV-/Lenex-Datei samt Besetzung.
 *
 * Eine Stelle für alle Importwege (Ergebnis-Import am Wettkampf, DSV-Import
 * der Trainer): Strecke je Schwimmer + Anzahl (relay_results-Konvention),
 * Mannschaftsnummer und Wettkampfart (Nachschwimmen) für Mannschaftswertungen
 * wie den DMS-J, Status aus dem Grund der Nichtwertung, Schwimmer als
 * Athleten – mit dem Portal-Schwimmer verknüpft, wenn DSV-ID oder Name +
 * Jahrgang eindeutig passen.
 */
class RelayResultWriter
{
    /** Grund der Nichtwertung (DSV) → Status in relay_results */
    private const STATUS = [
        'DS' => 'DQ', 'DQ' => 'DQ', 'DSQ' => 'DQ', 'DISQ' => 'DQ',
        'AB' => 'AB', 'WDR' => 'AB',
        'NA' => 'DNS', 'DNS' => 'DNS',
        'AU' => 'DNF', 'DNF' => 'DNF', 'ZU' => 'DNF',
    ];

    public function __construct(private AthleteMatchingService $athletes) {}

    /** @return bool true = neu angelegt */
    public function store(int $competitionId, string $clubName, array $relay, array $result): bool
    {
        $status = $result['status'] ? (self::STATUS[strtoupper($result['status'])] ?? 'DQ') : 'OK';

        $key = [
            'competition_id' => $competitionId,
            'club_name'      => $clubName,
            'discipline'     => $result['discipline'],
            'distance'       => (int) $result['distance'],
            'gender'         => in_array($relay['gender'] ?? null, ['M', 'F', 'D'], true) ? $relay['gender'] : null,
            'age_group'      => $result['age_group'] ?: null,
            'team_number'    => $result['team_number'] ?? $relay['team_number'] ?? null,
            'round'          => $result['round'] ?? null,
        ];

        $existing = RelayResult::where($key)->first();
        $model = $existing ?? new RelayResult($key);
        $model->fill([
            'relay_legs' => $result['relay_legs'] ?? null,
            'time_ms'    => $result['time_ms'] ?: null,
            'placement'  => $status === 'OK' ? ($result['place'] ?? null) : null,
            'status'     => $status,
        ])->save();

        $this->storeMembers($model, $relay['relay_members'] ?? [], $clubName);

        return $existing === null;
    }

    private function storeMembers(RelayResult $relay, array $members, string $clubName): void
    {
        foreach ($members as $m) {
            $leg = (int) ($m['leg'] ?? 0);
            if ($leg < 1 || ($m['lastname'] ?? '') === '') continue;

            $athlete = $this->athletes->findOrCreate([
                'lastname'   => $m['lastname'],
                'firstname'  => $m['firstname'],
                'birth_year' => (int) ($m['birthyear'] ?? 0),
                'gender'     => in_array($m['gender'] ?? null, ['M', 'F', 'D'], true) ? $m['gender'] : 'X',
                'dsv_id'     => $m['dsvid'] ?? null,
                'club'       => $clubName,
            ]);

            if (!$athlete->user_id && ($user = self::matchUser($m))) {
                $athlete->update(['user_id' => $user->id]);
            }

            DB::table('relay_members')->updateOrInsert(
                ['relay_result_id' => $relay->id, 'leg' => $leg],
                ['athlete_id' => $athlete->id]
            );
        }
    }

    /** Portal-Schwimmer: DSV-ID, sonst Vor- + Nachname (bei mehreren: Jahrgang) */
    public static function matchUser(array $m): ?User
    {
        if (!empty($m['dsvid']) && ($u = User::where('dsv_id', $m['dsvid'])->first())) return $u;

        $candidates = User::where('role', 'schwimmer')
            ->whereRaw('LOWER(firstname) = ?', [mb_strtolower(trim($m['firstname'] ?? ''))])
            ->whereRaw('LOWER(lastname) = ?', [mb_strtolower(trim($m['lastname'] ?? ''))])
            ->get();
        if ($candidates->count() === 1) return $candidates->first();

        $year = (int) ($m['birthyear'] ?? 0);
        return $year ? $candidates->first(fn($u) => $u->birth_date?->year === $year) : null;
    }
}
