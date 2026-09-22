<?php

namespace App\Services;

use App\Models\GroupMottoWeek;
use App\Models\Holiday;
use App\Models\Season;
use App\Models\TrainingGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Motto der Woche: je Woche ist eine Person an der Reihe.
 *
 * Wer mitmacht, entscheidet die Gruppe: ihre Schwimmer, auf Wunsch die
 * Trainer, und - wenn zwei Gruppen zusammen trainieren - zusaetzlich die
 * Mitglieder der Partnergruppe. Die Reihenfolge ist einstellbar
 * (TrainingGroup::mottoParticipants).
 */
class MottoWeekService
{
    /**
     * Legt fuer jede Trainingswoche der Saison eine Zeile an.
     * Bestehende Wochen bleiben unangetastet - auch ihre Zuweisung.
     */
    public function generateWeeks(TrainingGroup $group, Season $season): int
    {
        $participants = $group->mottoParticipants()->pluck('id')->all();
        if (empty($participants)) {
            return 0;
        }

        $existing = GroupMottoWeek::where('training_group_id', $group->id)
            ->whereDate('week_start', '>=', $season->start_date)
            ->whereDate('week_start', '<=', $season->end_date)
            ->get()
            ->keyBy(fn($w) => $w->week_start->format('Y-m-d'));

        $idx     = 0;
        $created = 0;

        foreach ($this->trainingWeeks($season) as $weekStart) {
            if (isset($existing[$weekStart])) {
                $idx++;   // Platz in der Reihenfolge verbrauchen, Zeile behalten
                continue;
            }

            GroupMottoWeek::create([
                'training_group_id' => $group->id,
                'user_id'           => $participants[$idx % count($participants)],
                'week_start'        => $weekStart,
            ]);
            $idx++;
            $created++;
        }

        return $created;
    }

    /**
     * Verteilt die kommenden Wochen neu - in der eingestellten Reihenfolge.
     *
     * Wochen mit bereits eingetragenem Motto bleiben, wie sie sind: dort hat
     * jemand schon Arbeit hineingesteckt.
     */
    public function redistributeMembers(TrainingGroup $group): int
    {
        $participants = $group->mottoParticipants()->pluck('id')->all();
        if (empty($participants)) {
            return 0;
        }

        $weeks = GroupMottoWeek::where('training_group_id', $group->id)
            ->where('week_start', '>=', now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'))
            ->whereNull('motto')
            ->orderBy('week_start')
            ->get();

        $changed = 0;
        foreach ($weeks as $i => $week) {
            $next = $participants[$i % count($participants)];
            if ((int) $week->user_id !== (int) $next) {
                $week->update(['user_id' => $next]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Die Gruppen, deren Motto-Zyklus diesen Benutzer betreffen: seine eigenen
     * und die, die seine Gruppe als Partnergruppe fuehren.
     */
    public function cycleGroupIdsFor(User $user): Collection
    {
        $own = $user->trainingGroups()->pluck('training_groups.id')
            ->merge($user->trainerGroups()->pluck('training_groups.id'))
            ->unique();

        if ($own->isEmpty()) {
            return collect();
        }

        return TrainingGroup::where('motto_week_enabled', true)
            ->where(function ($q) use ($own) {
                $q->whereIn('id', $own)
                  ->orWhereIn('motto_partner_group_id', $own);
            })
            ->pluck('id');
    }

    /**
     * Alle Montage der Saison, an denen trainiert wird.
     * Wochen, die komplett in den Ferien liegen, faellt kein Motto zu.
     *
     * @return array<int, string>  Montage als Y-m-d
     */
    private function trainingWeeks(Season $season): array
    {
        $holidays  = Holiday::intersecting($season->start_date, $season->end_date);
        $inHoliday = fn(Carbon $d) => $holidays->first(fn($h) => $h->containsDate($d));

        $monday = $season->start_date->copy()->startOfWeek(Carbon::MONDAY);
        if ($monday->lt($season->start_date)) {
            $monday->addWeek();
        }

        $weeks = [];
        while ($monday->lte($season->end_date)) {
            $allHoliday = true;
            for ($d = 0; $d < 5; $d++) {
                if (!$inHoliday($monday->copy()->addDays($d))) {
                    $allHoliday = false;
                    break;
                }
            }
            if (!$allHoliday) {
                $weeks[] = $monday->format('Y-m-d');
            }
            $monday->addWeek();
        }

        return $weeks;
    }
}
