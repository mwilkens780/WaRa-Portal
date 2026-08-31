<?php

namespace App\Services;

use App\Models\GroupMottoWeek;
use App\Models\Holiday;
use App\Models\Season;
use App\Models\TrainingGroup;
use Carbon\Carbon;

class MottoWeekService
{
    public function generateWeeks(TrainingGroup $group, Season $season): int
    {
        // All members = swimmers + trainers (active only)
        $members = $group->swimmers()
            ->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['users.id']);

        $trainers = $group->trainers()
            ->where('active', true)
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['users.id']);

        $allMembers = $members->merge($trainers)->pluck('id')->toArray();

        if (empty($allMembers)) {
            return 0;
        }

        // Get existing weeks to preserve mottos/assignments
        $existing = GroupMottoWeek::where('training_group_id', $group->id)
            ->whereDate('week_start', '>=', $season->start_date)
            ->whereDate('week_start', '<=', $season->end_date)
            ->get()
            ->keyBy(fn($w) => $w->week_start->format('Y-m-d'));

        // Fetch holidays for the season
        $holidays  = Holiday::intersecting($season->start_date, $season->end_date);
        $inHoliday = fn(Carbon $d) => $holidays->first(fn($h) => $h->containsDate($d));

        // Walk all Mondays in the season
        $monday = $season->start_date->copy()->startOfWeek(Carbon::MONDAY);
        if ($monday->lt($season->start_date)) {
            $monday->addWeek();
        }

        $weeks = [];
        while ($monday->lte($season->end_date)) {
            // Skip weeks where all Mon–Fri fall in holiday
            $weekInHoliday = true;
            for ($d = 0; $d < 5; $d++) {
                if (!$inHoliday($monday->copy()->addDays($d))) {
                    $weekInHoliday = false;
                    break;
                }
            }
            if (!$weekInHoliday) {
                $weeks[] = $monday->format('Y-m-d');
            }
            $monday->addWeek();
        }

        // Assign members round-robin, preserving existing assignments
        $idx     = 0;
        $created = 0;

        foreach ($weeks as $weekStart) {
            if (isset($existing[$weekStart])) {
                $idx++;  // advance index but don't override existing record
                continue;
            }
            GroupMottoWeek::create([
                'training_group_id' => $group->id,
                'user_id'           => $allMembers[$idx % count($allMembers)],
                'week_start'        => $weekStart,
            ]);
            $idx++;
            $created++;
        }

        return $created;
    }

    public function redistributeMembers(TrainingGroup $group): void
    {
        $weeks = GroupMottoWeek::where('training_group_id', $group->id)
            ->where('week_start', '>=', now()->startOfWeek(Carbon::MONDAY))
            ->orderBy('week_start')
            ->get();

        if ($weeks->isEmpty()) {
            return;
        }

        $members = $group->swimmers()->where('active', true)->get(['users.id']);
        $trainers = $group->trainers()->where('active', true)->get(['users.id']);
        $allIds   = $members->merge($trainers)->pluck('id')->toArray();

        if (empty($allIds)) {
            return;
        }

        foreach ($weeks as $i => $week) {
            $week->update(['user_id' => $allIds[$i % count($allIds)]]);
        }
    }
}
