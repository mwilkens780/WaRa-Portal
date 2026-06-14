<?php

namespace App\Services\Competition;

use App\Models\CompetitionEntry;
use App\Models\User;

class EntryValidationService
{
    /**
     * Validate all entries of a swimmer for a competition.
     *
     * Returns an array of warnings, each with:
     *   type: 'qualifying_time' | 'session_conflict' | 'age_class_mismatch'
     *   message: string
     *   entry_ids: int[]
     */
    public function validate(int $userId, int $competitionId): array
    {
        $warnings = [];

        $entries = CompetitionEntry::with('competitionEvent')
            ->where('competition_id', $competitionId)
            ->where('user_id', $userId)
            ->where('status', 'entered')
            ->get();

        $user = User::find($userId);

        foreach ($entries as $entry) {
            $event = $entry->competitionEvent;

            // 1. Qualifying time not reached
            if ($event && $event->qualifying_time_ms > 0 && $entry->entry_time_ms) {
                if ($entry->entry_time_ms > $event->qualifying_time_ms) {
                    $diff = $entry->entry_time_ms - $event->qualifying_time_ms;
                    $warnings[] = [
                        'type'      => 'qualifying_time',
                        'entry_ids' => [$entry->id],
                        'message'   => "WK {$event->event_number}: Pflichtzeit nicht erreicht "
                            . "(fehlen " . $this->formatMs($diff) . ")",
                    ];
                }
            }

            // 2. Session collision: same session_number as another entry
            foreach ($entries as $other) {
                if ($other->id <= $entry->id) continue;
                $otherEvent = $other->competitionEvent;
                if ($event && $otherEvent
                    && $event->session_number === $otherEvent->session_number
                    && $event->session_number !== null) {
                    $warnings[] = [
                        'type'      => 'session_conflict',
                        'entry_ids' => [$entry->id, $other->id],
                        'message'   => "WK {$event->event_number} und WK {$otherEvent->event_number} "
                            . "liegen im selben Abschnitt (Abschnitt {$event->session_number})",
                    ];
                }
            }

            // 3. Age class mismatch
            if ($user && $entry->age_group && is_numeric($entry->age_group)) {
                $swimmerYear = $user->birth_date?->year;
                if ($swimmerYear && (int)$entry->age_group !== $swimmerYear) {
                    $warnings[] = [
                        'type'      => 'age_class_mismatch',
                        'entry_ids' => [$entry->id],
                        'message'   => "Jahrgang {$swimmerYear} passt nicht zu Wertungsklasse {$entry->age_group}",
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * Validate all swimmers for a competition and return a keyed array.
     * Returns [ user_id => warnings[] ]
     */
    public function validateAll(int $competitionId): array
    {
        $userIds = CompetitionEntry::where('competition_id', $competitionId)
            ->where('status', 'entered')
            ->distinct()
            ->pluck('user_id');

        $result = [];
        foreach ($userIds as $uid) {
            $warnings = $this->validate($uid, $competitionId);
            if ($warnings) {
                $result[$uid] = $warnings;
            }
        }
        return $result;
    }

    private function formatMs(int $ms): string
    {
        $sec = intdiv($ms, 1_000);
        $hun = intdiv($ms % 1_000, 10);
        return sprintf('%d,%02d s', $sec, $hun);
    }
}
