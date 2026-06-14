<?php

namespace App\Services\Import;

use App\Models\Athlete;
use App\Models\User;

class AthleteMatchingService
{
    /**
     * Find an existing athlete or create a new one.
     *
     * Priority:
     *   1. DSV-ID match with an own club member (user) → athlete linked to user
     *   2. Exact (lastname, firstname, birth_year, gender) unique key
     */
    public function findOrCreate(array $row): Athlete
    {
        // 1. DSV-ID → look for own swimmer
        if (!empty($row['dsv_id'])) {
            $user = User::where('dsv_id', $row['dsv_id'])->first();
            if ($user) {
                return Athlete::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'lastname'   => $user->lastname  ?? $row['lastname'],
                        'firstname'  => $user->firstname ?? $row['firstname'],
                        'birth_year' => $user->birth_date?->year ?? $row['birth_year'],
                        'gender'     => $user->gender ?? $row['gender'],
                        'club_name'  => $row['club'] ?? null,
                        'nationality'=> $row['nationality'] ?? 'GER',
                    ]
                );
            }
        }

        // 2. Name + birth_year + gender as unique identifier
        return Athlete::firstOrCreate(
            [
                'lastname'   => $row['lastname'],
                'firstname'  => $row['firstname'],
                'birth_year' => $row['birth_year'],
                'gender'     => $row['gender'],
            ],
            [
                'club_name'   => $row['club'] ?? null,
                'nationality' => $row['nationality'] ?? 'GER',
            ]
        );
    }

    /**
     * Try to link an existing Athlete to a User after the fact (e.g., when a new
     * user with a matching DSV-ID is created).
     */
    public function linkToUser(Athlete $athlete, User $user): void
    {
        if ($athlete->user_id === null) {
            $athlete->update(['user_id' => $user->id]);
        }
    }
}
