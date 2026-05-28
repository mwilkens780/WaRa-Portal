<?php

namespace App\Services;

use App\Models\CompetitionResult;
use App\Models\Record;
use Illuminate\Support\Facades\DB;

class RecordCheckService
{
    /**
     * Check a single newly imported/entered result against all records.
     * Updates Vereinsrekorde automatically; marks if Landesrekord is beaten.
     */
    public function checkResult(CompetitionResult $result): void
    {
        if ($result->time_ms <= 0) return;
        if (!$result->gender || $result->gender === 'X') return;

        $course    = $result->competition->course ?? 'LCM';
        $ageGroup  = $result->age_group ?: null;

        $this->checkVr($result, $course, $ageGroup);
        $this->checkLr($result, $course, $ageGroup);
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

            // Unmark the previous record holder for this category
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

    /**
     * Re-check ALL existing competition results against current records.
     * Called after bulk record import. Resets all flags and recomputes.
     */
    public function recheckAll(): void
    {
        DB::transaction(function () {
            CompetitionResult::query()->update([
                'breaks_vereinsrekord' => false,
                'breaks_landesrekord'  => false,
            ]);

            // Reset VR competition_result_id pointers (will be restored below)
            Record::where('type', 'vereinsrekord')->update(['competition_result_id' => null]);

            // Load all valid in-system results with their competition (for course + date)
            $results = CompetitionResult::with(['user', 'competition'])
                ->where('time_ms', '>', 0)
                ->whereNotNull('gender')
                ->whereIn('gender', ['M', 'F'])
                ->get();

            // Group by record category key, then find the best result per category
            $grouped = $results->groupBy(fn($r) => implode('§', [
                $r->discipline,
                $r->distance,
                $r->gender,
                $r->age_group ?? '',
                $r->competition?->course ?? 'LCM',
            ]));

            foreach ($grouped as $key => $group) {
                $best = $group->sortBy('time_ms')->first();
                [$discipline, $distance, $gender, $ag, $course] = explode('§', $key, 5);
                $ageGroup = $ag === '' ? null : $ag;

                // --- Vereinsrekord ---
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
                    // No VR imported for this category — create from best in-system result
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

                // --- Landesrekord ---
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
        });
    }
}
