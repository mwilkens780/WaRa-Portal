<?php

namespace App\Services\Competition;

use App\Models\CompetitionEntry;
use App\Models\CompetitionRelayEntry;
use App\Models\CompetitionRelayEntryMember;
use App\Models\CompetitionResult;
use App\Models\SwimmingTime;
use Illuminate\Support\Facades\DB;

class EntryService
{
    /**
     * Create or update a single-event entry for a swimmer.
     */
    public function setEntry(int $competitionId, int $userId, array $params): CompetitionEntry
    {
        $entryTime = $params['entry_time_ms'] ?? $this->getBestEntryTime(
            $userId,
            $params['discipline'],
            $params['distance']
        );

        return CompetitionEntry::updateOrCreate(
            [
                'competition_id' => $competitionId,
                'user_id'        => $userId,
                'discipline'     => $params['discipline'],
                'distance'       => (int) $params['distance'],
                'age_group'      => $params['age_group'] ?? null,
            ],
            [
                'competition_event_id' => $params['competition_event_id'] ?? null,
                'gender'               => $params['gender'],
                'entry_time_ms'        => $entryTime,
                'status'               => 'entered',
                'created_by_id'        => $params['created_by_id'] ?? null,
            ]
        );
    }

    /**
     * Mark an entry as scratched (soft delete).
     */
    public function scratchEntry(int $entryId): void
    {
        CompetitionEntry::where('id', $entryId)->update(['status' => 'scratched']);
    }

    /**
     * Delete an entry entirely.
     */
    public function deleteEntry(int $entryId): void
    {
        CompetitionEntry::destroy($entryId);
    }

    /**
     * Create or replace a relay entry with its members.
     */
    public function setRelayEntry(int $competitionId, array $params, array $memberUserIds): CompetitionRelayEntry
    {
        return DB::transaction(function () use ($competitionId, $params, $memberUserIds) {
            $relay = CompetitionRelayEntry::updateOrCreate(
                [
                    'competition_id' => $competitionId,
                    'discipline'     => $params['discipline'],
                    'distance'       => (int) $params['distance'],
                    'gender'         => $params['gender'],
                    'age_group'      => $params['age_group'] ?? null,
                ],
                [
                    'competition_event_id' => $params['competition_event_id'] ?? null,
                    'entry_time_ms'        => $params['entry_time_ms'] ?? null,
                    'status'               => 'entered',
                    'notes'                => $params['notes'] ?? null,
                    'created_by_id'        => $params['created_by_id'] ?? null,
                ]
            );

            // Replace members
            CompetitionRelayEntryMember::where('relay_entry_id', $relay->id)->delete();
            foreach ($memberUserIds as $position => $userId) {
                if (!$userId) continue;
                CompetitionRelayEntryMember::create([
                    'relay_entry_id' => $relay->id,
                    'user_id'        => (int) $userId,
                    'position'       => (int) $position + 1,
                ]);
            }

            return $relay->fresh('members');
        });
    }

    /**
     * Find the best available entry time for a swimmer in a given discipline/distance.
     * Searches competition_results first, then swimming_times.
     */
    public function getBestEntryTime(int $userId, string $discipline, int $distance): ?int
    {
        $fromResults = CompetitionResult::where('user_id', $userId)
            ->where('discipline', $discipline)
            ->where('distance', $distance)
            ->where('time_ms', '>', 0)
            ->min('time_ms');

        $fromTimes = SwimmingTime::where('user_id', $userId)
            ->where('discipline', $discipline)
            ->where('distance', $distance)
            ->where('time_ms', '>', 0)
            ->min('time_ms');

        $best = array_filter([$fromResults, $fromTimes]);
        return $best ? min($best) : null;
    }
}
