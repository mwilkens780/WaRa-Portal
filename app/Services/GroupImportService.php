<?php

namespace App\Services;

use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GroupImportService
{
    // ── Parse CSV into preview data ────────────────────────────────────────

    public function parse(string $path, TrainingGroup $group): array
    {
        $raw = file_get_contents($path);
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252') ?: $raw;
        }

        $currentMemberIds = $group->swimmers()->pluck('users.id')->toArray();

        $rows       = [];
        $seenIds    = [];
        $headerSeen = false;

        $handle = fopen('data://text/plain;base64,' . base64_encode($raw), 'r');
        if ($handle) {
            while (($fields = fgetcsv($handle, 0, ';')) !== false) {
                $fields = array_map('trim', $fields);
                if (implode('', $fields) === '') continue;

                // Skip all rows until the actual header row (Name;Jg.;...) is found
                if (!$headerSeen) {
                    if (mb_strtolower($fields[0]) === 'name') {
                        $headerSeen = true;
                    }
                    continue;
                }

                $csvName  = $fields[0] ?? '';
                $csvYear  = (int)($fields[1] ?? 0) ?: null;
                $csvDsvId = trim($fields[2] ?? '');
                $csvActive = $this->parseBool($fields[3] ?? '');

                if (!$csvName) continue;

                [$user, $status, $candidates] = $this->matchUser($csvName, $csvYear, $csvDsvId);

                if ($user) $seenIds[] = $user->id;

                $rows[] = [
                    'csv_name'   => $csvName,
                    'csv_year'   => $csvYear,
                    'csv_dsv_id' => $csvDsvId,
                    'csv_active' => $csvActive,
                    'user_id'    => $user?->id,
                    'user_name'  => $user ? ($user->firstname . ' ' . $user->lastname) : null,
                    'user_year'  => $user?->birth_date?->format('Y'),
                    'status'     => $status,            // matched|unmatched|ambiguous
                    'candidates' => $candidates,        // array for ambiguous
                    'in_group'   => $user ? in_array($user->id, $currentMemberIds) : false,
                    'action'     => $status === 'matched' ? 'include' : 'skip',
                ];
            }
            fclose($handle);
        }

        // Members in group but not in CSV → will be removed
        $toRemove = [];
        foreach ($currentMemberIds as $memberId) {
            if (!in_array($memberId, $seenIds)) {
                $u = User::find($memberId);
                if ($u) {
                    $toRemove[] = [
                        'user_id' => $memberId,
                        'name'    => $u->firstname . ' ' . $u->lastname,
                        'year'    => $u->birth_date?->format('Y'),
                        'remove'  => true,
                    ];
                }
            }
        }

        return ['rows' => $rows, 'to_remove' => $toRemove];
    }

    // ── Execute import ─────────────────────────────────────────────────────

    /**
     * @param array $formRows  Form input per row: [action, user_id, new_firstname, new_lastname, new_email]
     * @param array $formRemove Form input per to-remove entry: boolean (remove or keep)
     */
    public function execute(
        TrainingGroup $group,
        array $parsedRows,
        array $parsedToRemove,
        array $formRows,
        array $formRemove
    ): array {
        $added = 0; $removed = 0; $updated = 0; $created = 0;

        foreach ($parsedRows as $i => $row) {
            $formRow = $formRows[$i] ?? [];
            $action  = $formRow['action'] ?? $row['action'];

            if ($action === 'skip') continue;

            if ($action === 'create') {
                // Create new swimmer from CSV data
                $fn = trim($formRow['new_firstname'] ?? $this->guessFirstname($row['csv_name']));
                $ln = trim($formRow['new_lastname']  ?? $this->guessLastname($row['csv_name']));
                $email = trim($formRow['new_email'] ?? '');
                if (!$email) {
                    $email = 'import.' . Str::slug($fn . '.' . $ln) . '.' . Str::random(6) . '@placeholder.local';
                }

                $newUser = User::create([
                    'firstname'  => $fn,
                    'lastname'   => $ln,
                    'email'      => $email,
                    'password'   => Hash::make(Str::random(16)),
                    'role'       => 'schwimmer',
                    'active'     => $row['csv_active'],
                    'birth_date' => $row['csv_year'] ? ($row['csv_year'] . '-01-01') : null,
                    'dsv_id'     => $row['csv_dsv_id'] ?: null,
                ]);
                // Swimmer may only be in one group
                $newUser->trainingGroups()->detach();
                $group->swimmers()->syncWithoutDetaching([$newUser->id]);
                $created++;
                continue;
            }

            // action = 'include' with existing user
            $userId = (int)($formRow['user_id'] ?? $row['user_id'] ?? 0);
            if (!$userId) continue;

            $user = User::find($userId);
            if (!$user) continue;

            // Update dsv_id if provided
            if ($row['csv_dsv_id'] && !$user->dsv_id) {
                $user->dsv_id = $row['csv_dsv_id'];
            }

            // Sync active status
            if ($user->active !== $row['csv_active']) {
                $user->active = $row['csv_active'];
                $updated++;
            }

            if ($user->isDirty()) $user->save();

            // Add to group (move from other groups if needed)
            if (!$row['in_group']) {
                $user->trainingGroups()->detach(); // one-group constraint
                $group->swimmers()->syncWithoutDetaching([$userId]);
                $added++;
            }
        }

        // Remove members not in CSV (if user confirmed)
        foreach ($parsedToRemove as $i => $entry) {
            $shouldRemove = isset($formRemove[$i]) ? (bool)$formRemove[$i] : $entry['remove'];
            if ($shouldRemove) {
                $group->swimmers()->detach($entry['user_id']);
                $removed++;
            }
        }

        return compact('added', 'removed', 'updated', 'created');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function matchUser(string $csvName, ?int $csvYear, string $csvDsvId): array
    {
        // 1. DSV-Id exact match
        if ($csvDsvId) {
            $hit = User::where('role', 'schwimmer')->where('dsv_id', $csvDsvId)->first();
            if ($hit) return [$hit, 'matched', []];
        }

        // Parse name components
        [$firstname, $lastname] = $this->splitName($csvName);

        $base = User::where('role', 'schwimmer');

        // Build name condition (try both field orders)
        $nameMatch = fn($q) => $q
            ->where(fn($q2) => $q2
                ->whereRaw('LOWER(lastname) = ?',  [strtolower($lastname)])
                ->whereRaw('LOWER(firstname) LIKE ?', [strtolower($firstname) . '%'])
            )->orWhere(fn($q2) => $q2
                ->whereRaw('LOWER(firstname) = ?',  [strtolower($firstname)])
                ->whereRaw('LOWER(lastname) LIKE ?', [strtolower($lastname) . '%'])
            );

        // 2. Name + birth year
        if ($csvYear) {
            $results = (clone $base)->where($nameMatch)->whereYear('birth_date', $csvYear)->get();
            if ($results->count() === 1) return [$results->first(), 'matched', []];
            if ($results->count() > 1)  return [null, 'ambiguous', $results->toArray()];
        }

        // 3. Name only
        $results = (clone $base)->where($nameMatch)->get();
        if ($results->count() === 1) return [$results->first(), 'matched', []];
        if ($results->count() > 1)  return [null, 'ambiguous', $results->toArray()];

        return [null, 'unmatched', []];
    }

    private function splitName(string $name): array
    {
        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));
            return [$first, $last];
        }
        $parts = explode(' ', trim($name), 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    private function guessFirstname(string $name): string
    {
        return $this->splitName($name)[0];
    }

    private function guessLastname(string $name): string
    {
        return $this->splitName($name)[1];
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['ja', 'yes', '1', 'x', 'aktiv', 'true', 'j'], true);
    }
}
