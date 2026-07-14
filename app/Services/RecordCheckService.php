<?php

namespace App\Services;

use App\Models\BestListEntry;
use App\Models\CompetitionResult;
use App\Models\Record;
use Illuminate\Support\Facades\DB;

class RecordCheckService
{
    private const BEST_LIST_TOP_N = 10;

    public function checkResult(CompetitionResult $result): void
    {
        if ($result->time_ms <= 0) return;
        if (!$result->gender || $result->gender === 'X') return;

        $course   = $result->competition->course ?? 'Langbahn';
        $ageGroup = $result->age_group ?: null;

        $this->checkVr($result, $course, $ageGroup);
        $this->checkLr($result, $course, $ageGroup);
        $this->checkBestLists($result, $course);
    }

    private function checkVr(CompetitionResult $result, string $course, ?string $ageGroup): void
    {
        $vr = Record::where('type', 'vereinsrekord')
            ->where('discipline', $result->discipline)
            ->where('distance', $result->distance)
            ->where('gender', $result->gender)
            ->where('age_group', $ageGroup)
            ->where('course', $course)
            ->first();

        if (!$vr || $result->time_ms < $vr->time_ms) {
            Record::updateOrCreate(
                [
                    'type'       => 'vereinsrekord',
                    'discipline' => $result->discipline,
                    'distance'   => $result->distance,
                    'gender'     => $result->gender,
                    'age_group'  => $ageGroup,
                    'course'     => $course,
                ],
                [
                    'swimmer_name'          => $result->user->name,
                    'user_id'               => $result->user_id,
                    'time_ms'               => $result->time_ms,
                    'set_date'              => $result->competition->date ?? null,
                    'location'              => $result->competition->location ?? null,
                    'competition_result_id' => $result->id,
                ]
            );

            CompetitionResult::where('breaks_vereinsrekord', true)
                ->where('id', '!=', $result->id)
                ->where('discipline', $result->discipline)
                ->where('distance', $result->distance)
                ->where('gender', $result->gender)
                ->where('age_group', $ageGroup)
                ->whereHas('competition', fn($q) => $q->where('course', $course))
                ->update(['breaks_vereinsrekord' => false]);

            $result->update(['breaks_vereinsrekord' => true]);
        }
    }

    private function checkLr(CompetitionResult $result, string $course, ?string $ageGroup): void
    {
        $lr = Record::where('type', 'landesrekord')
            ->where('discipline', $result->discipline)
            ->where('distance', $result->distance)
            ->where('gender', $result->gender)
            ->where('age_group', $ageGroup)
            ->where('course', $course)
            ->first();

        if ($lr && $result->time_ms < $lr->time_ms) {
            $result->update(['breaks_landesrekord' => true]);
        }
    }

    private function checkBestLists(CompetitionResult $result, string $course): void
    {
        $birthYear = $result->user?->birth_date?->year;
        if (!$birthYear) return;

        $compDate = $result->competition?->date;
        $setYear  = $compDate?->year;
        if (!$setYear) return;

        // Already in the list?
        $alreadyExists = BestListEntry::where('competition_result_id', $result->id)->exists();
        if ($alreadyExists) return;

        $baseKey = [
            'discipline' => $result->discipline,
            'distance'   => $result->distance,
            'gender'     => $result->gender,
            'birth_year' => $birthYear,
            'course'     => $course,
        ];

        foreach (['eternal', 'annual'] as $listType) {
            $query = BestListEntry::where('list_type', $listType)->where($baseKey);
            if ($listType === 'annual') {
                $query->where('set_year', $setYear);
            }

            $count   = $query->count();
            $slowest = $query->orderByDesc('time_ms')->first();

            // Qualifies if list has fewer than 10 entries OR this result is faster than the slowest
            if ($count < self::BEST_LIST_TOP_N || ($slowest && $result->time_ms < $slowest->time_ms)) {
                BestListEntry::create(array_merge($baseKey, [
                    'list_type'             => $listType,
                    'set_year'              => $listType === 'annual' ? $setYear : null,
                    'swimmer_name'          => $result->user->name,
                    'user_id'               => $result->user_id,
                    'time_ms'               => $result->time_ms,
                    'set_date'              => $compDate,
                    'location'              => $result->competition?->location,
                    'competition_result_id' => $result->id,
                ]));

                // Drop excess entries (keep only top N)
                $entries = BestListEntry::where('list_type', $listType)->where($baseKey)
                    ->when($listType === 'annual', fn($q) => $q->where('set_year', $setYear))
                    ->orderBy('time_ms')
                    ->get();

                if ($entries->count() > self::BEST_LIST_TOP_N) {
                    $entries->slice(self::BEST_LIST_TOP_N)->each->delete();
                }
            }
        }
    }

    public function recheckAll(): void
    {
        DB::transaction(function () {
            CompetitionResult::query()->update([
                'breaks_vereinsrekord' => false,
                'breaks_landesrekord'  => false,
            ]);

            Record::where('type', 'vereinsrekord')->update(['competition_result_id' => null]);

            $results = CompetitionResult::with(['user', 'competition'])
                ->where('time_ms', '>', 0)
                ->whereNotNull('gender')
                ->whereIn('gender', ['M', 'F'])
                ->get();

            $grouped = $results->groupBy(fn($r) => implode('§', [
                $r->discipline,
                $r->distance,
                $r->gender,
                $r->age_group ?? '',
                $r->competition?->course ?? 'Langbahn',
            ]));

            foreach ($grouped as $key => $group) {
                $best = $group->sortBy('time_ms')->first();
                [$discipline, $distance, $gender, $ag, $course] = explode('§', $key, 5);
                $ageGroup = $ag === '' ? null : $ag;

                $vr = Record::where('type', 'vereinsrekord')
                    ->where('discipline', $discipline)
                    ->where('distance', (int)$distance)
                    ->where('gender', $gender)
                    ->where('age_group', $ageGroup)
                    ->where('course', $course)
                    ->first();

                if ($vr) {
                    if ($best->time_ms <= $vr->time_ms) {
                        $vr->update([
                            'swimmer_name'          => $best->user?->name ?? $vr->swimmer_name,
                            'user_id'               => $best->user_id,
                            'time_ms'               => $best->time_ms,
                            'set_date'              => $best->competition?->date ?? $vr->set_date,
                            'location'              => $best->competition?->location ?? $vr->location,
                            'competition_result_id' => $best->id,
                        ]);
                        $best->update(['breaks_vereinsrekord' => true]);
                    }
                } else {
                    Record::create([
                        'type'                  => 'vereinsrekord',
                        'discipline'            => $discipline,
                        'distance'              => (int)$distance,
                        'gender'                => $gender,
                        'age_group'             => $ageGroup,
                        'course'                => $course,
                        'swimmer_name'          => $best->user?->name ?? '–',
                        'user_id'               => $best->user_id,
                        'time_ms'               => $best->time_ms,
                        'set_date'              => $best->competition?->date,
                        'location'              => $best->competition?->location,
                        'competition_result_id' => $best->id,
                    ]);
                    $best->update(['breaks_vereinsrekord' => true]);
                }

                $lr = Record::where('type', 'landesrekord')
                    ->where('discipline', $discipline)
                    ->where('distance', (int)$distance)
                    ->where('gender', $gender)
                    ->where('age_group', $ageGroup)
                    ->where('course', $course)
                    ->first();

                if ($lr && $best->time_ms < $lr->time_ms) {
                    $best->update(['breaks_landesrekord' => true]);
                }
            }

            $this->recheckBestLists($results);
        });
    }

    public function recheckBestLists($results = null): void
    {
        BestListEntry::whereNotNull('competition_result_id')->delete();

        if ($results === null) {
            $results = CompetitionResult::with(['user', 'competition'])
                ->where('time_ms', '>', 0)
                ->whereNotNull('gender')
                ->whereIn('gender', ['M', 'F'])
                ->get();
        }

        foreach ($results as $result) {
            if (!$result->user?->birth_date) continue;
            $birthYear = $result->user->birth_date->year;
            $compDate  = $result->competition?->date;
            $setYear   = $compDate?->year;
            if (!$setYear) continue;

            $course = $result->competition?->course ?? 'Langbahn';

            $baseKey = [
                'discipline' => $result->discipline,
                'distance'   => $result->distance,
                'gender'     => $result->gender,
                'birth_year' => $birthYear,
                'course'     => $course,
            ];

            foreach (['eternal', 'annual'] as $listType) {
                $sameYear = $listType === 'annual' ? $setYear : null;

                $entry = BestListEntry::where('list_type', $listType)
                    ->where($baseKey)
                    ->when($listType === 'annual', fn($q) => $q->where('set_year', $setYear))
                    ->where('competition_result_id', $result->id)
                    ->first();

                if (!$entry) {
                    BestListEntry::create(array_merge($baseKey, [
                        'list_type'             => $listType,
                        'set_year'              => $sameYear,
                        'swimmer_name'          => $result->user->name,
                        'user_id'               => $result->user_id,
                        'time_ms'               => $result->time_ms,
                        'set_date'              => $compDate,
                        'location'              => $result->competition?->location,
                        'competition_result_id' => $result->id,
                    ]));
                }
            }
        }

        // Trim each category to top 10
        $categories = BestListEntry::whereNotNull('competition_result_id')
            ->selectRaw('list_type, discipline, distance, gender, birth_year, course, set_year')
            ->groupBy('list_type', 'discipline', 'distance', 'gender', 'birth_year', 'course', 'set_year')
            ->get();

        foreach ($categories as $cat) {
            $entries = BestListEntry::where('list_type', $cat->list_type)
                ->where('discipline', $cat->discipline)
                ->where('distance', $cat->distance)
                ->where('gender', $cat->gender)
                ->where('birth_year', $cat->birth_year)
                ->where('course', $cat->course)
                ->where(function ($q) use ($cat) {
                    if ($cat->set_year) {
                        $q->where('set_year', $cat->set_year);
                    } else {
                        $q->whereNull('set_year');
                    }
                })
                ->whereNotNull('competition_result_id')
                ->orderBy('time_ms')
                ->get();

            if ($entries->count() > self::BEST_LIST_TOP_N) {
                $entries->slice(self::BEST_LIST_TOP_N)->each->delete();
            }
        }
    }
}
