<?php

namespace App\Support;

use App\Models\Season;
use App\Models\SwimmerSeriesExclusion;
use App\Models\TrainingSession;
use App\Models\TrainingSessionSwimmer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Trainingsbeteiligung eines Sportlers.
 *
 * Eine Rechnung fuer alle Ansichten. Vorher rechnete jede fuer sich: das
 * Dashboard ueber die Saison und die laufende Woche, die Trainingsseite ueber
 * die gesamte Vereinszeit - fuer denselben Sportler standen damit am selben Tag
 * verschiedene Prozentzahlen auf dem Bildschirm.
 *
 * Gezaehlt wird der Anteil der besuchten an den *angebotenen* Einheiten der
 * eigenen Gruppen, einmal fuer die laufende Saison (ab der ersten Einheit) und
 * einmal fuer den laufenden Monat (ab dem Ersten).
 *
 * Was zum Angebot zaehlt:
 *  - Einheiten der eigenen Trainingsgruppen,
 *  - Einheiten, zu denen der Sportler einzeln oder als Serie eingeteilt ist.
 *
 * Was nicht dazu zaehlt:
 *  - offene Einheiten ohne Gruppe - die sind ein Angebot an alle, keine
 *    Verpflichtung der eigenen Gruppe,
 *  - dauerhaft abgemeldete Serien - wer sich aus einer Serie abgemeldet hat,
 *    bekommt sie nicht als Fehlzeit angerechnet,
 *  - Einheiten, die noch nicht vorbei sind: Das Training von heute Abend ist am
 *    Vormittag keine verpasste Einheit.
 */
final class TrainingParticipation
{
    /** Beide Zeitraeume auf einmal - so kommen sie immer als Paar in die Ansicht. */
    public static function forSwimmer(User $swimmer): array
    {
        return [
            'season' => self::season($swimmer),
            'month'  => self::month($swimmer),
        ];
    }

    public static function season(User $swimmer): array
    {
        $season = Season::current();

        if (!$season) {
            return self::emptyResult('Saison');
        }

        return self::inRange($swimmer, $season->start_date, $season->end_date, 'Saison ' . $season->name);
    }

    public static function month(User $swimmer): array
    {
        return self::inRange($swimmer, now()->startOfMonth(), now()->endOfMonth(),
            now()->isoFormat('MMMM YYYY'));
    }

    // ── innen ────────────────────────────────────────────────────────────────

    /**
     * @return array{attended:int,offered:int,pct:int,from:?Carbon,label:string}
     */
    private static function inRange(User $swimmer, $von, $bis, string $label): array
    {
        $angebot = self::offered($swimmer)
            ->whereDate('date', '>=', Carbon::parse($von)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($bis)->toDateString());

        $offered  = (clone $angebot)->count();
        $attended = (clone $angebot)
            ->whereHas('attendances', fn($a) => $a->where('user_id', $swimmer->id)->where('attended', true))
            ->count();

        // "Ab der ersten Trainingseinheit": nicht ab dem Saisonstart auf dem
        // Papier, sondern ab dem Tag, an dem tatsaechlich trainiert wurde.
        $erste = $offered > 0 ? Carbon::parse((clone $angebot)->min('date')) : null;

        return [
            'attended' => $attended,
            'offered'  => $offered,
            'pct'      => $offered > 0 ? (int) round($attended / $offered * 100) : 0,
            'from'     => $erste,
            'label'    => $label,
        ];
    }

    /** Die angebotenen, beendeten Einheiten dieses Sportlers. */
    private static function offered(User $swimmer): Builder
    {
        $gruppenIds = $swimmer->trainingGroups()->pluck('training_groups.id');
        $einzelIds  = TrainingSessionSwimmer::where('user_id', $swimmer->id)
            ->whereNotNull('training_session_id')->pluck('training_session_id');
        $serienIds  = TrainingSessionSwimmer::where('user_id', $swimmer->id)
            ->whereNotNull('recurrence_group_id')->pluck('recurrence_group_id');

        $q = TrainingSession::query()->finished()->takingPlace();

        // Ohne Gruppe und ohne Einteilung gibt es kein Angebot - und ohne diese
        // Bremse wuerde die leere Bedingung unten alle Einheiten einsammeln.
        if ($gruppenIds->isEmpty() && $einzelIds->isEmpty() && $serienIds->isEmpty()) {
            return $q->whereRaw('1 = 0');
        }

        $q->where(function ($w) use ($gruppenIds, $einzelIds, $serienIds) {
            if ($gruppenIds->isNotEmpty()) {
                $w->orWhereHas('trainingGroups', fn($g) => $g->whereIn('training_groups.id', $gruppenIds));
            }
            if ($einzelIds->isNotEmpty()) {
                $w->orWhereIn('id', $einzelIds);
            }
            if ($serienIds->isNotEmpty()) {
                $w->orWhereIn('recurrence_group_id', $serienIds);
            }
        });

        $abgemeldet = SwimmerSeriesExclusion::where('user_id', $swimmer->id)->pluck('recurrence_group_id');

        if ($abgemeldet->isNotEmpty()) {
            $q->where(function ($w) use ($abgemeldet, $einzelIds) {
                $w->whereNull('recurrence_group_id')
                  ->orWhereNotIn('recurrence_group_id', $abgemeldet);
                if ($einzelIds->isNotEmpty()) {
                    // Eine einzelne Zusage wiegt schwerer als die Serienabmeldung
                    $w->orWhereIn('id', $einzelIds);
                }
            });
        }

        return $q;
    }

    private static function emptyResult(string $label): array
    {
        return ['attended' => 0, 'offered' => 0, 'pct' => 0, 'from' => null, 'label' => $label];
    }
}
