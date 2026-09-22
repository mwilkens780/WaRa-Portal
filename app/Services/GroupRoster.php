<?php

namespace App\Services;

use App\Models\Season;
use App\Models\TrainingGroup;
use App\Models\TrainingGroupGoal;
use App\Models\TrainingGroupGoalEvaluation;
use App\Models\User;
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
                // Wer die Gruppe waehrend der Saison verlassen hat, gehoert
                // nicht zur Aufstellung am Saisonende.
                DB::table('training_group_season_members')
                    ->where('training_group_id', $group->id)
                    ->where('season_id', $season->id)
                    ->whereNotIn('user_id', $current ?: [0])
                    ->delete();

                $now  = now();
                $rows = array_map(fn($uid) => [
                    'training_group_id' => $group->id,
                    'season_id'         => $season->id,
                    'user_id'           => $uid,
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
     * @return array{swimmers: Collection<User>, source: string}
     */
    public function swimmersFor(TrainingGroup $group, ?Season $season): array
    {
        if (!$this->isPast($season)) {
            return [
                'swimmers' => $group->swimmers()->where('active', true)->orderBy('lastname')->orderBy('firstname')->get(),
                'source'   => self::SOURCE_LIVE,
            ];
        }

        $ids = DB::table('training_group_season_members')
            ->where('training_group_id', $group->id)
            ->where('season_id', $season->id)
            ->pluck('user_id');

        if ($ids->isNotEmpty()) {
            // Ohne active-Filter: ausgeschiedene Sportler bleiben sichtbar
            return [
                'swimmers' => User::whereIn('id', $ids)->orderBy('lastname')->orderBy('firstname')->get(),
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
            'source'   => self::SOURCE_EVALUATIONS,
        ];
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
