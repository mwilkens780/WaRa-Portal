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

        $toProcess = array_filter($rows, fn($r) => $r['action'] !== 'skip');

        DB::transaction(function () use ($toProcess, &$created, &$updated, &$errors) {
            foreach ($toProcess as $row) {
                try {
                    $data  = $row['data'];
                    $roles = $row['roles'] ?? [];

                    if ($row['action'] === 'update') {
                        $user = User::find($row['user_id']);
                        if (!$user) continue;

                        // Never overwrite existing values with empty/null from import.
                        // Only apply fields that actually have a value in the CSV.
                        $updateData = array_filter($data, fn($v) => $v !== null && $v !== '' && $v !== []);

                        // Never change email for existing users via import
                        unset($updateData['email']);

                        $user->fill($updateData)->save();
                        $updated++; // count immediately — syncRoles is secondary
                        if ($roles) {
                            $user->syncRoles($roles);
                        }
                    } else {
                        $plain                    = self::generateInitialPassword();
                        $data['password']         = Hash::make($plain);
                        $data['initial_password'] = $plain;
                        $data['active']           = $data['active'] ?? true;
                        $user = User::create($data);
                        $created++; // count immediately — syncRoles is secondary
                        if ($roles) {
                            $user->syncRoles($roles);
                        }
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
        // ── Identity ─────────────────────────────────────────────────────────
        $lastname    = trim($row['Name']       ?? '');
        $firstname   = trim($row['Vorname']    ?? '');
        $dsvId       = trim($row['DSV-ID']     ?? '');
        if ($dsvId !== '' && preg_match('/^0+$/', $dsvId)) $dsvId = ''; // 0 / 000000 = kein gültiger Wert
        $memberNr    = trim($row['Mitgliedsnummer'] ?? '');
        $displayName = trim("$firstname $lastname");

        // ── Contact ───────────────────────────────────────────────────────────
        $email   = trim($row['Mail1']     ?? '');
        $email2  = trim($row['Mail2']     ?? '');
        $phone   = trim($row['Telefon1']  ?? '');
        $mobile  = trim($row['Mobil']     ?? '');

        // ── Address ───────────────────────────────────────────────────────────
        $street     = trim($row['Strasse'] ?? '');
        $postalCode = trim($row['Plz']     ?? '');
        $city       = trim($row['Ort']     ?? '');
        $country    = trim($row['Land']    ?? '');

        // ── Demographics ──────────────────────────────────────────────────────
        $gender    = $this->parseGender($row['Geschlecht'] ?? '');
        $birthDate = $this->parseDate($row['Geburtstag'] ?? '');
        $joinDate  = $this->parseDate($row['Eintritt']   ?? '');
        $leaveDate = $this->parseDate($row['Austritt']   ?? '');

        // ── WebClub-Gruppe für Schwimmer ──────────────────────────────────────
        $group = trim($row['GruppeSchwimmer'] ?? '');

        // ── Roles (priority: trainer > vorstand > kampfrichter > schwimmer) ──
        $isSwimmer     = $this->parseBool($row['AktiverSchwimmer']     ?? '');
        $isTrainer     = $this->parseBool($row['AktiverTrainer']       ?? '');
        $isReferee     = $this->parseBool($row['AktiverKampfrichter']  ?? '');
        $isFunctionary = $this->parseBool($row['AktiverFunktionär']    ?? '');

        $activeRoles = array_values(array_filter([
            $isTrainer     ? 'trainer'      : null,
            $isFunctionary ? 'vorstand'     : null,
            $isReferee     ? 'kampfrichter' : null,
            $isSwimmer     ? 'schwimmer'    : null,
        ]));

        $role            = $activeRoles[0] ?? null;
        $additionalRoles = count($activeRoles) > 1 ? array_slice($activeRoles, 1) : null;

        // ── Trainer-specific certifications ───────────────────────────────────
        $trainerLicenseNr         = trim($row['TrainerLizenzNr']      ?? '');
        $trainerLicenseValidUntil = $this->parseDate($row['TrainerLizenzGueltig']    ?? '');
        $rescueCertificateUntil   = $this->parseDate($row['RettungsnachweisBis']     ?? '');
        $firstAidUntil            = $this->parseDate($row['ErsteHilfeBis']           ?? '');
        $policeClearanceDate      = $this->parseDate($row['FührungszeugnisVom']      ?? '');

        // ── Miscellaneous ─────────────────────────────────────────────────────
        $notes = trim($row['Bemerkung'] ?? '');

        // ── Active status ─────────────────────────────────────────────────────
        $active = !$leaveDate || Carbon::parse($leaveDate)->isFuture();

        // ── Find existing user: name + birthdate only ────────────────────────
        // DSV-ID is NOT used for matching — it is not a reliable unique key
        // (many users share 000000 or have no valid ID at all).
        $existing  = null;
        $matchedBy = null;
        if ($birthDate && $lastname) {
            $existing = User::where('lastname', $lastname)
                ->where('firstname', $firstname)
                ->where('birth_date', $birthDate)
                ->first();
            if ($existing) $matchedBy = 'name_birthdate';
        }

        // ── Build data array ──────────────────────────────────────────────────
        $data = array_filter([
            'firstname'                   => $firstname   ?: null,
            'lastname'                    => $lastname    ?: null,
            'name'                        => $displayName ?: null,
            'gender'                      => $gender,
            'birth_date'                  => $birthDate,
            'dsv_id'                      => $dsvId       ?: null,
            'membership_number'           => $memberNr    ?: null,
            'member_since'                => $joinDate,
            'training_group'              => $group       ?: null,
            'phone'                       => $phone       ?: null,
            'mobile'                      => $mobile      ?: null,
            'email2'                      => $email2      ?: null,
            'street'                      => $street      ?: null,
            'postal_code'                 => $postalCode  ?: null,
            'city'                        => $city        ?: null,
            'country'                     => $country     ?: null,
            'trainer_license_nr'          => $trainerLicenseNr       ?: null,
            'trainer_license_valid_until' => $trainerLicenseValidUntil,
            'rescue_certificate_until'    => $rescueCertificateUntil,
            'first_aid_until'             => $firstAidUntil,
            'police_clearance_date'       => $policeClearanceDate,
            'notes'                       => $notes       ?: null,
            'active'                      => $active,
            'additional_roles'            => $additionalRoles,
        ], fn($v) => $v !== null);

        if ($role) {
            $data['role'] = $role;
        }

        // ── Email: only set for new users when CSV has an actual value ───────
        // Never generate dummy emails — leave email null if not in CSV.
        if (!$existing && $email) {
            $data['email'] = $email;
        }

        // ── Determine action ──────────────────────────────────────────────────
        if ($role === null) {
            $action = 'skip';
            $reason = 'Keine WebClub-Rolle erkannt (AktiverSchwimmer / AktiverTrainer / AktiverKampfrichter / AktiverFunktionär alle 0) – Rolle manuell zuweisen oder überspringen';
        } else {
            $action = $existing ? 'update' : 'new';
            $reason = null;
        }

        return [
            'action'         => $action,
            'name'           => $displayName,
            'role'           => $role,
            'roles'          => $activeRoles,
            'dsv_id'         => $dsvId,
            'email'          => $existing ? $existing->email : ($email ?: null),
            'user_id'        => $existing?->id,
            'existing_name'  => $existing ? trim($existing->firstname . ' ' . $existing->lastname) : null,
            'matched_by'     => $matchedBy,
            'reason'         => $reason,
            'data'           => $data,
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
