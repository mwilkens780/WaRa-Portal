<?php

namespace App\Services;

use App\Models\User;
use App\Services\TraceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WebClubImportService
{
    /**
     * Parse the CSV and return a preview collection.
     * Each row: ['action' => new|update|skip, 'name', 'role'|null, 'dsv_id', 'email', 'user_id'?, 'reason'?, 'data']
     *
     * Skip rows still carry populated 'data' so the controller can import them
     * when the admin assigns a role manually in the preview.
     */
    public function parse(string $path): array
    {
        $raw = file_get_contents($path);

        // WebClub exports Windows-1252; convert to UTF-8 if necessary
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        // Strip BOM
        $raw = ltrim($raw, "\xEF\xBB\xBF");

        $lines = preg_split('/\r\n|\n|\r/', trim($raw));
        if (empty($lines)) {
            throw new \RuntimeException('CSV-Datei ist leer.');
        }

        $headers = str_getcsv(array_shift($lines), ';');
        $headers = array_map('trim', $headers);

        $rows  = [];
        $stats = ['new' => 0, 'update' => 0, 'skip' => 0];

        // Track DSV-IDs seen in this CSV to flag in-file duplicates
        $seenDsvIds = [];

        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            $cols = str_getcsv($line, ';');
            while (count($cols) < count($headers)) $cols[] = '';
            $row = array_combine($headers, $cols);

            $parsed = $this->parseRow($row);

            // Flag duplicate DSV-IDs within the same CSV
            $dsvId = $parsed['dsv_id'] ?? '';
            if ($dsvId && isset($seenDsvIds[$dsvId])) {
                $parsed['action'] = 'skip';
                $parsed['reason'] = "DSV-ID {$dsvId} kommt in dieser Datei mehrfach vor (Duplikat übersprungen)";
                $parsed['role']   = null;
            } elseif ($dsvId) {
                $seenDsvIds[$dsvId] = true;
            }

            if ($parsed['action'] === 'skip')        $stats['skip']++;
            elseif ($parsed['action'] === 'update')  $stats['update']++;
            else                                     $stats['new']++;

            $rows[] = $parsed;
        }

        return ['rows' => $rows, 'stats' => $stats];
    }

    /**
     * Execute the import inside a transaction.
     * Returns ['created', 'updated', 'skipped', 'errors' => [['name', 'message'], ...]].
     */
    public function execute(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];

        // Collect only the rows we actually need to touch
        $toProcess = array_filter($rows, fn($r) => $r['action'] !== 'skip');

        DB::transaction(function () use ($toProcess, &$created, &$updated, &$skipped, &$errors) {
            foreach ($toProcess as $row) {
                try {
                    $data = $row['data'];

                    if ($row['action'] === 'update') {
                        // Use Eloquent model update to fire audit events
                        $user = User::find($row['user_id']);
                        if ($user) {
                            $user->fill($data)->save();
                        }
                        $updated++;
                    } else {
                        $plain                    = self::generateInitialPassword();
                        $data['password']         = Hash::make($plain);
                        $data['initial_password'] = $plain;
                        $data['active']           = $data['active'] ?? true;
                        User::create($data);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $humanMsg = $this->humaniseError($e->getMessage());
                    $errors[] = ['name' => $row['name'] ?? '?', 'message' => $humanMsg];
                    TraceService::warning("WebClub-Import: {$humanMsg}", [
                        'row_name' => $row['name'] ?? '?',
                        'action'   => $row['action'],
                        'dsv_id'   => $row['dsv_id'] ?? null,
                    ]);
                }
            }
        });

        $skipped = count($rows) - count($toProcess);

        return compact('created', 'updated', 'skipped', 'errors');
    }

    private function humaniseError(string $msg): string
    {
        if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'dsv_id')) {
            return 'DSV-ID bereits in der Datenbank vorhanden';
        }
        if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'email')) {
            return 'E-Mail-Adresse bereits vergeben';
        }
        if (str_contains($msg, 'Duplicate entry')) {
            return 'Doppelter Datensatz (Unique-Constraint)';
        }
        // Trim noisy SQL details for the UI
        return Str::limit($msg, 120);
    }

    private function parseRow(array $row): array
    {
        $lastname  = trim($row['Name'] ?? '');
        $firstname = trim($row['Vorname'] ?? '');
        $dsvId     = trim($row['DSV-ID'] ?? '');
        $email     = trim($row['Mail1'] ?? '');
        $phone     = trim($row['Telefon1'] ?? '') ?: trim($row['Mobil'] ?? '');
        $memberNr  = trim($row['Mitgliedsnummer'] ?? '');
        $group     = trim($row['GruppeSchwimmer'] ?? '');
        $isSwimmer = $this->parseBool($row['AktiverSchwimmer'] ?? '');
        $isTrainer = $this->parseBool($row['AktiverTrainer'] ?? '');
        $gender    = $this->parseGender($row['Geschlecht'] ?? '');
        $birthDate = $this->parseDate($row['Geburtstag'] ?? '');
        $joinDate  = $this->parseDate($row['Eintritt'] ?? '');
        $leaveDate = $this->parseDate($row['Austritt'] ?? '');

        $displayName = trim("$firstname $lastname");

        // Determine role from WebClub flags (null = not detected)
        if ($isTrainer) {
            $role = 'trainer';
        } elseif ($isSwimmer) {
            $role = 'schwimmer';
        } else {
            $role = null;
        }

        // Active: leave date empty or in the future
        $active = !$leaveDate || Carbon::parse($leaveDate)->isFuture();

        // Find existing user: DSV-ID first, then name + birthdate
        $existing = null;
        if ($dsvId) {
            $existing = User::where('dsv_id', $dsvId)->first();
        }
        if (!$existing && $birthDate && $lastname) {
            $existing = User::where('lastname', $lastname)
                ->where('firstname', $firstname)
                ->where('birth_date', $birthDate)
                ->first();
        }

        // Build base data (role added below if detected)
        $data = array_filter([
            'firstname'         => $firstname ?: null,
            'lastname'          => $lastname ?: null,
            'name'              => $displayName ?: null,
            'gender'            => $gender,
            'birth_date'        => $birthDate,
            'dsv_id'            => $dsvId ?: null,
            'membership_number' => $memberNr ?: null,
            'member_since'      => $joinDate,
            'training_group'    => $group ?: null,
            'phone'             => $phone ?: null,
            'active'            => $active,
        ], fn($v) => $v !== null);

        if ($role) {
            $data['role'] = $role;
        }

        // For new users (no existing match), pre-compute email
        if (!$existing) {
            if (!$email) {
                $slug  = Str::slug($firstname . ' ' . $lastname, '.');
                $email = $slug . '@mitglied.wasserratten.intern';
            }
            $data['email'] = $email;
        }

        // Determine action
        if ($role === null) {
            $action = 'skip';
            $reason = 'Keine WebClub-Rolle erkannt – Rolle manuell zuweisen oder überspringen';
        } else {
            $action = $existing ? 'update' : 'new';
            $reason = null;
        }

        return [
            'action'  => $action,
            'name'    => $displayName,
            'role'    => $role,
            'dsv_id'  => $dsvId,
            'email'   => $existing ? $existing->email : ($data['email'] ?? null),
            'user_id' => $existing?->id,
            'reason'  => $reason,
            'data'    => $data,
        ];
    }

    public static function generateInitialPassword(): string
    {
        $upper  = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 3);
        $digits = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
        $lower  = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz'), 0, 3);
        return $upper . $digits . $lower;
    }

    private function parseBool(string $val): bool
    {
        return in_array(strtolower(trim($val)), ['1', 'ja', 'yes', 'true', 'x'], true);
    }

    private function parseGender(string $val): ?string
    {
        return match(strtolower(trim($val))) {
            'm', 'männlich', 'male'        => 'M',
            'w', 'f', 'weiblich', 'female' => 'F',
            default                        => null,
        };
    }

    private function parseDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '' || $val === '00.00.0000') return null;
        try {
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $val)) {
                return Carbon::createFromFormat('d.m.Y', $val)->format('Y-m-d');
            }
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
