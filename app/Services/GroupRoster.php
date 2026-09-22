<?php

namespace App\Services;

use App\Models\Season;
use App\Models\TrainingGroup;
use App\Models\TrainingGroupGoal;
use App\Models\TrainingGroupGoalEvaluation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wer gehoerte in einer Saison zu einer Trainingsgruppe - und welche
 * Leistungskriterien galten dort?
 *
 * Laufende (und kommende) Saison: die heutige Gruppe, live.
 * Vergangene Saison: die gespeicherte Aufstellung zum Saisonende aus
 * training_group_season_members. Wer seitdem gewechselt ist, den Verein
 * verlassen hat oder deaktiviert wurde, bleibt dort sichtbar; wer spaeter
 * dazukam, taucht nicht auf.
 */
class GroupRoster
{
    public const SOURCE_LIVE        = 'live';
    public const SOURCE_SNAPSHOT    = 'snapshot';
    public const SOURCE_EVALUATIONS = 'evaluations';

    /**
     * Schreibt die Aufstellung der Saison fort, in der heute liegt. Laeuft
     * taeglich spaet abends - der letzte Lauf am letzten Saisontag ist damit
     * der eingefrorene Stand.
     *
     * Bewusst nur fuer eine Saison, die heute *laeuft*: In der Sommerpause
     * zwischen zwei Saisons wird nichts geschrieben, sonst wuerden
     * Umbesetzungen der Sommerpause die abgeschlossene Saison verfaelschen.
     */
    public function snapshotRunningSeason(): int
    {
        $season = Season::forDate(now());
        if (!$season) {
            return 0;
        }

        $written = 0;

        foreach (TrainingGroup::with(['swimmers' => fn($q) => $q->where('active', true)])->get() as $group) {
            $current = $group->swimmers->pluck('id')->all();

            DB::transaction(function () use ($group, $season, $current, &$written) {
                $now   = now();
                $scope = fn() => DB::table('training_group_season_members')
                    ->where('training_group_id', $group->id)
                    ->where('season_id', $season->id);

                // Nicht mehr in der Gruppe (gewechselt, deaktiviert, ausgetreten):
                // Zeile bleibt, bekommt aber das Austrittsdatum - so bleibt die
                // Person samt Bewertung in der Rueckschau sichtbar.
                $scope()->whereNull('left_at')
                    ->whereNotIn('user_id', $current ?: [0])
                    ->update(['left_at' => today(), 'updated_at' => $now]);

                // Zurueckgekehrt: wieder regulaeres Mitglied
                if ($current) {
                    $scope()->whereNotNull('left_at')
                        ->whereIn('user_id', $current)
                        ->update(['left_at' => null, 'updated_at' => $now]);
                }

                $rows = array_map(fn($uid) => [
                    'training_group_id' => $group->id,
                    'season_id'         => $season->id,
                    'user_id'           => $uid,
                    'left_at'           => null,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ], $current);

                if ($rows) {
                    DB::table('training_group_season_members')->insertOrIgnore($rows);
                }
                $written += count($rows);
            });
        }

        return $written;
    }

    public function isPast(?Season $season): bool
    {
        return $season !== null && $season->end_date->lt(today());
    }

    /**
     * Sportler der Gruppe in der Saison.
     *
     * swimmers: die Gruppe (laufend: heute, vergangen: am Saisonende) - sie
     *           bestimmt die Gruppengroesse.
     * leavers:  waehrend der Saison ausgeschieden, jeweils mit ->left_at.
     *           Sichtbar mit Bewertung, zaehlen aber nicht zur Gruppe.
     *
     * @return array{swimmers: Collection<User>, leavers: Collection<User>, source: string}
     */
    public function swimmersFor(TrainingGroup $group, ?Season $season): array
    {
        $rows = $season
            ? DB::table('training_group_season_members')
                ->where('training_group_id', $group->id)
                ->where('season_id', $season->id)
                ->get(['user_id', 'left_at'])
            : collect();

        if (!$this->isPast($season)) {
            $swimmers = $group->swimmers()->where('active', true)
                ->orderBy('lastname')->orderBy('firstname')->get();

            return [
                'swimmers' => $swimmers,
                'leavers'  => $this->leavers($rows, $swimmers->pluck('id')),
                'source'   => self::SOURCE_LIVE,
            ];
        }

        if ($rows->isNotEmpty()) {
            $memberIds = $rows->whereNull('left_at')->pluck('user_id');

            // Ohne active-Filter: wer den Verein inzwischen verlassen hat, bleibt sichtbar
            return [
                'swimmers' => User::whereIn('id', $memberIds)->orderBy('lastname')->orderBy('firstname')->get(),
                'leavers'  => $this->leavers($rows, $memberIds),
                'source'   => self::SOURCE_SNAPSHOT,
            ];
        }

        // Saison vor Einfuehrung der Aufstellung: Wer bewertet wurde, war dabei.
        // Mehr laesst sich nachtraeglich nicht rekonstruieren.
        $evaluated = TrainingGroupGoalEvaluation::where('season_id', $season->id)
            ->whereIn('training_group_goal_id', $group->goals()->pluck('id'))
            ->distinct()->pluck('user_id');

        return [
            'swimmers' => User::whereIn('id', $evaluated)->orderBy('lastname')->orderBy('firstname')->get(),
            'leavers'  => collect(),
            'source'   => self::SOURCE_EVALUATIONS,
        ];
    }

    /**
     * Ausgeschiedene aus den Saisonzeilen - ohne die, die (wieder) Mitglied
     * sind.
     *
     * Bewusst eigene Objekte statt eines Zusatzattributs am User: ein
     * versehentliches save() wuerde sonst versuchen, left_at in users zu
     * schreiben.
     *
     * @return Collection<object{user: User, left_at: Carbon}>
     */
    private function leavers(Collection $rows, Collection $memberIds): Collection
    {
        $left = $rows->whereNotNull('left_at')
            ->reject(fn($r) => $memberIds->contains($r->user_id))
            ->keyBy('user_id');

        if ($left->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $left->keys())
            ->orderBy('lastname')->orderBy('firstname')->get()
            ->map(fn($u) => (object) [
                'user'    => $u,
                'left_at' => Carbon::parse($left[$u->id]->left_at),
            ]);
    }

    /**
     * Leistungskriterien, die in der Saison fuer die Gruppen galten.
     *
     * Laufend: alle aktiven. Vergangen: was zum Saisonende schon angelegt und
     * noch aktiv ist, plus alles, was in der Saison bewertet wurde - auch
     * wenn es inzwischen archiviert ist.
     */
    public function criteriaQuery(Collection $groupIds, ?Season $season)
    {
        $q = TrainingGroupGoal::whereIn('training_group_id', $groupIds);

        if (!$this->isPast($season)) {
            return $q->where('active', true);
        }

        return $q->where(function ($w) use ($season) {
            $w->where(fn($a) => $a->where('active', true)
                                  ->where('created_at', '<=', $season->end_date->copy()->endOfDay()))
              ->orWhereHas('evaluations', fn($e) => $e->where('season_id', $season->id));
        });
    }
}
