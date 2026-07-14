<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionEvent;
use App\Models\CompetitionResult;
use App\Models\ImportLog;
use App\Models\Season;
use App\Models\Setting;
use App\Models\User;
use App\Services\TraceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class WebClubCrawler
{
    private const SOURCE = 'webclub_crawler';

    // ── Öffentliche API ──────────────────────────────────────────────────────

    public function run(): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'persons_synced' => 0];

        if (!Setting::getBool('crawler.webclub.enabled', false)) {
            Log::info('WebClubCrawler: deaktiviert – übersprungen.');
            return $stats;
        }

        $config = $this->buildConfig();

        if (!$config['base_url'] || !$config['username'] || !$config['password_encrypted']) {
            $msg = 'WebClub-Crawler: Zugangsdaten unvollständig (base_url, username, password).';
            Log::warning($msg);
            ImportLog::create(['source' => self::SOURCE, 'status' => 'error', 'message' => $msg]);
            $stats['errors']++;
            return $stats;
        }

        try {
            $output = $this->callPlaywright($config);
        } catch (\Throwable $e) {
            $msg = 'Playwright-Fehler: ' . $e->getMessage();
            Log::error('WebClubCrawler: ' . $msg);
            ImportLog::create(['source' => self::SOURCE, 'status' => 'error', 'message' => $msg]);
            $stats['errors']++;
            return $stats;
        }

        DB::transaction(function () use ($output, $config, &$stats) {
            foreach ($output['competitions'] ?? [] as $raw) {
                try {
                    $result = $this->syncCompetition($raw, $config);
                    if ($result === 'created' || $result === 'updated') $stats['imported']++;
                    else $stats['skipped']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::error('WebClubCrawler Wettkampf-Sync: ' . $e->getMessage(), $raw);
                }
            }

            $personStats = $this->syncPersons($output['persons'] ?? []);
            $stats['persons_synced'] = $personStats['synced'];
            $stats['errors']        += $personStats['errors'];
        });

        foreach ($output['errors'] ?? [] as $err) {
            Log::warning('WebClubCrawler (JS): ' . ($err['type'] ?? '?') . ' – ' . ($err['message'] ?? ''));
            $stats['errors']++;
        }

        return $stats;
    }

    // ── Wettkämpfe ───────────────────────────────────────────────────────────

    private function syncCompetition(array $raw, array $config): string
    {
        $webclubId = $raw['webclub_id'] ?? null;
        $name      = trim($raw['name'] ?? '');
        $date      = $raw['date'] ?? null;

        if (!$name || !$date) {
            return 'skipped';
        }

        // Vorhandenen Wettkampf finden: erst per webclub_id, dann per Name+Datum
        $competition = null;
        if ($webclubId) {
            $competition = Competition::where('webclub_event_id', $webclubId)->first();
        }
        if (!$competition) {
            $competition = Competition::where('name', $name)
                ->whereDate('date', $date)
                ->first();
        }

        $season = $date ? Season::forDate(Carbon::parse($date)) : Season::current();

        if (!$competition) {
            // Neu anlegen
            $competition = Competition::create(array_filter([
                'name'             => $name,
                'date'             => $date,
                'date_end'         => $raw['date_end'] ?? null,
                'location'         => $raw['location'] ?? null,
                'course'           => $this->normalizeCourse($raw['course'] ?? null),
                'organizer'        => $raw['organizer'] ?? null,
                'meldeschluss'     => $raw['meldeschluss'] ?? null,
                'description'      => $raw['description'] ?? null,
                'source_url'       => $raw['webclub_url'] ?? null,
                'webclub_event_id' => $webclubId,
                'season_id'        => $season?->id,
                'type'             => 'regional',
            ], fn($v) => $v !== null && $v !== ''));

            ImportLog::create([
                'source'         => self::SOURCE,
                'source_url'     => $raw['webclub_url'] ?? null,
                'filename'       => null,
                'status'         => 'success',
                'competition_id' => $competition->id,
                'message'        => 'Wettkampf neu angelegt via WebClub-Crawler.',
            ]);

            $this->syncEntries($competition, $raw['entries'] ?? []);
            $this->syncResults($competition, $raw['results'] ?? []);

            TraceService::info("WebClubCrawler: Wettkampf neu angelegt – {$name}", ['id' => $competition->id]);
            return 'created';
        }

        // Vorhandenen Wettkampf ergänzen (nur NULL-Felder befüllen, nie überschreiben)
        $updates = [];

        if (!$competition->webclub_event_id && $webclubId) {
            $updates['webclub_event_id'] = $webclubId;
        }
        if (empty($competition->location)    && !empty($raw['location']))    $updates['location']    = $raw['location'];
        if (empty($competition->course)      && !empty($raw['course']))      $updates['course']      = $this->normalizeCourse($raw['course']);
        if (empty($competition->organizer)   && !empty($raw['organizer']))   $updates['organizer']   = $raw['organizer'];
        if (empty($competition->meldeschluss)&& !empty($raw['meldeschluss']))$updates['meldeschluss']= $raw['meldeschluss'];
        if (empty($competition->description) && !empty($raw['description'])) $updates['description'] = $raw['description'];
        if (empty($competition->date_end)    && !empty($raw['date_end']))    $updates['date_end']    = $raw['date_end'];
        if (empty($competition->season_id)   && $season)                     $updates['season_id']   = $season->id;
        if (empty($competition->source_url)  && !empty($raw['webclub_url'])) $updates['source_url']  = $raw['webclub_url'];

        if ($updates) {
            $competition->update($updates);
            ImportLog::create([
                'source'         => self::SOURCE,
                'source_url'     => $raw['webclub_url'] ?? null,
                'status'         => 'success',
                'competition_id' => $competition->id,
                'message'        => 'Wettkampf ergänzt: ' . implode(', ', array_keys($updates)),
            ]);
        } else {
            ImportLog::create([
                'source'         => self::SOURCE,
                'source_url'     => $raw['webclub_url'] ?? null,
                'status'         => 'skipped',
                'competition_id' => $competition->id,
                'message'        => 'Wettkampf bereits vollständig – keine Änderung.',
            ]);
        }

        $this->syncEntries($competition, $raw['entries'] ?? []);
        $this->syncResults($competition, $raw['results'] ?? []);

        return $updates ? 'updated' : 'skipped';
    }

    private function syncEntries(Competition $competition, array $entries): void
    {
        foreach ($entries as $entry) {
            if (empty($entry['athlete_name'])) continue;

            $user = $this->findUser($entry);
            if (!$user) continue;

            // Veranstaltungs-Event ermitteln
            $event = $this->findOrSkipEvent($competition, $entry);

            // Nur anlegen, wenn noch kein Eintrag existiert
            $exists = CompetitionEntry::where('competition_id', $competition->id)
                ->where('user_id', $user->id)
                ->when($event, fn($q) => $q->where('competition_event_id', $event->id))
                ->exists();

            if ($exists) continue;

            CompetitionEntry::create(array_filter([
                'competition_id'       => $competition->id,
                'user_id'              => $user->id,
                'competition_event_id' => $event?->id,
                'entry_time_ms'        => $entry['entry_time_ms'] ?? $entry['time_ms'] ?? null,
                'status'               => 'entered',
            ]));
        }
    }

    private function syncResults(Competition $competition, array $results): void
    {
        foreach ($results as $result) {
            if (empty($result['athlete_name']) || empty($result['time_ms'])) continue;

            $user = $this->findUser($result);
            if (!$user) continue;

            $event = $this->findOrSkipEvent($competition, $result);
            if (!$event) continue;

            // Nur anlegen, wenn noch kein Ergebnis existiert
            $exists = CompetitionResult::where('competition_id', $competition->id)
                ->where('user_id', $user->id)
                ->where('discipline', $event->discipline)
                ->where('distance', $event->distance)
                ->exists();

            if ($exists) continue;

            CompetitionResult::create(array_filter([
                'competition_id' => $competition->id,
                'user_id'        => $user->id,
                'discipline'     => $event->discipline,
                'distance'       => $event->distance,
                'gender'         => $result['gender'] ?? $event->gender ?? null,
                'time_ms'        => $result['time_ms'],
                'placement'      => $result['placement'] ?? null,
            ]));
        }
    }

    private function findOrSkipEvent(Competition $competition, array $item): ?CompetitionEvent
    {
        $eventLabel = $item['event_label'] ?? null;
        if (!$eventLabel) return null;

        // Versuche Disziplin + Distanz aus Label zu parsen: "100 Freistil" o.ä.
        $discipline = $this->parseDisciplineFromLabel($eventLabel);
        $distance   = $this->parseDistanceFromLabel($eventLabel);

        if (!$discipline || !$distance) return null;

        return CompetitionEvent::where('competition_id', $competition->id)
            ->where('discipline', $discipline)
            ->where('distance', $distance)
            ->first();
    }

    // ── Personen ─────────────────────────────────────────────────────────────

    private function syncPersons(array $persons): array
    {
        $synced = 0;
        $errors = 0;

        foreach ($persons as $raw) {
            try {
                $result = $this->syncPerson($raw);
                if ($result) $synced++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('WebClubCrawler Personen-Sync: ' . $e->getMessage(), $raw);
            }
        }

        if ($synced > 0 || $errors > 0) {
            ImportLog::create([
                'source'  => self::SOURCE,
                'status'  => $errors > 0 ? 'error' : 'success',
                'message' => "Personen-Sync: {$synced} ergänzt, {$errors} Fehler.",
            ]);
        }

        return compact('synced', 'errors');
    }

    private function syncPerson(array $raw): bool
    {
        $webclubId = $raw['webclub_person_id'] ?? null;
        $lastname  = trim($raw['lastname']  ?? '');
        $firstname = trim($raw['firstname'] ?? '');
        $birthDate = $raw['birth_date'] ?? null;

        if (!$lastname && !$firstname) return false;

        // Vorhandenen User finden
        $user = null;
        if ($webclubId) {
            $user = User::where('webclub_person_id', $webclubId)->first();
        }
        if (!$user && $lastname && $birthDate) {
            $user = User::where('lastname', $lastname)
                ->where('firstname', $firstname)
                ->where('birth_date', $birthDate)
                ->first();
        }

        if (!$user) {
            // Unbekannte Person: im WebClub-Crawler werden keine neuen User angelegt
            // (das ist Aufgabe des manuellen CSV-Imports via WebClubImportService)
            return false;
        }

        // Nur NULL-Felder befüllen
        $updates = [];
        if (!$user->webclub_person_id && $webclubId)                                $updates['webclub_person_id'] = $webclubId;
        if (empty($user->gender)            && !empty($raw['gender']))              $updates['gender']            = $raw['gender'];
        if (empty($user->dsv_id)            && !empty($raw['dsv_id']))              $updates['dsv_id']            = $raw['dsv_id'];
        if (empty($user->membership_number) && !empty($raw['membership_number']))   $updates['membership_number'] = $raw['membership_number'];
        if (empty($user->member_since)      && !empty($raw['member_since']))        $updates['member_since']      = $raw['member_since'];
        if (empty($user->training_group)    && !empty($raw['training_group']))      $updates['training_group']    = $raw['training_group'];
        if (empty($user->phone)             && !empty($raw['phone']))               $updates['phone']             = $raw['phone'];
        if (empty($user->mobile)            && !empty($raw['mobile']))              $updates['mobile']            = $raw['mobile'];
        if (empty($user->street)            && !empty($raw['street']))              $updates['street']            = $raw['street'];
        if (empty($user->postal_code)       && !empty($raw['postal_code']))         $updates['postal_code']       = $raw['postal_code'];
        if (empty($user->city)              && !empty($raw['city']))                $updates['city']              = $raw['city'];

        if ($updates) {
            $user->update($updates);
            return true;
        }

        return false;
    }

    // ── Playwright-Aufruf ────────────────────────────────────────────────────

    private function callPlaywright(array $config): array
    {
        $configFile = tempnam(sys_get_temp_dir(), 'webclub_');
        // Passwort entschlüsseln für den Prozess
        $runtimeConfig = $config;
        if (isset($runtimeConfig['password_encrypted'])) {
            try {
                $runtimeConfig['password'] = Crypt::decryptString($runtimeConfig['password_encrypted']);
            } catch (\Throwable) {
                throw new \RuntimeException('WebClub-Passwort konnte nicht entschlüsselt werden.');
            }
            unset($runtimeConfig['password_encrypted']);
        }

        file_put_contents($configFile, json_encode($runtimeConfig));

        $scriptPath = base_path('scripts/webclub-crawler.js');
        $env        = ['PLAYWRIGHT_BROWSERS_PATH' => '/opt/pw-browsers', 'HOME' => '/root'];
        $nodePath   = $this->resolveNodePath();

        $process = new Process(
            [$nodePath, $scriptPath, $configFile],
            null,
            $env
        );

        $process->setTimeout(intval(Setting::getCached('crawler.webclub.timeout_seconds', 300)));

        try {
            $process->run();
        } finally {
            @unlink($configFile);
        }

        if (!$process->isSuccessful()) {
            $err = trim($process->getErrorOutput());
            throw new \RuntimeException($err ?: 'Playwright-Prozess mit Code ' . $process->getExitCode() . ' beendet.');
        }

        $json = $process->getOutput();
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Ungültige JSON-Ausgabe des Crawlers: ' . json_last_error_msg());
        }

        return $data;
    }

    // ── Node.js discovery ────────────────────────────────────────────────────

    private function resolveNodePath(): string
    {
        // Allow explicit override via admin setting
        $configured = trim(Setting::getCached('crawler.webclub.node_path', ''));
        if ($configured !== '') {
            return $configured;
        }

        // Try well-known paths in order (PATH-resolved 'node' first, then absolutes)
        $candidates = [
            'node',
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/opt/node22/bin/node',
            '/opt/node20/bin/node',
            '/opt/node18/bin/node',
        ];

        foreach ($candidates as $candidate) {
            $check = new Process(['which', $candidate]);
            $check->run();
            if ($check->isSuccessful() && trim($check->getOutput()) !== '') {
                return $candidate;
            }
            // For absolute paths, also check file existence directly
            if (str_starts_with($candidate, '/') && is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'Node.js wurde nicht gefunden. Bitte den Pfad unter Einstellungen → Crawler → node_path konfigurieren.'
        );
    }

    // ── Config ───────────────────────────────────────────────────────────────

    private function buildConfig(): array
    {
        return [
            'base_url'              => Setting::getCached('crawler.webclub.base_url', ''),
            'username'              => Setting::getCached('crawler.webclub.username', ''),
            'password_encrypted'    => Setting::getCached('crawler.webclub.password', ''),
            'lookback_days'         => (int) Setting::getCached('crawler.webclub.lookback_days', 90),
            'lookahead_days'        => (int) Setting::getCached('crawler.webclub.lookahead_days', 365),
            'scrape_competitions'   => Setting::getBool('crawler.webclub.scrape_competitions', true),
            'scrape_persons'        => Setting::getBool('crawler.webclub.scrape_persons', true),
            'headless'              => Setting::getBool('crawler.webclub.headless', true),
            'timeout_ms'            => (int) Setting::getCached('crawler.webclub.timeout_ms', 15000),
            'screenshot_on_error'   => Setting::getCached('crawler.webclub.screenshot_path', null),
        ];
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    private function findUser(array $item): ?User
    {
        $webclubId = $item['webclub_person_id'] ?? null;

        if ($webclubId) {
            $user = User::where('webclub_person_id', $webclubId)->first();
            if ($user) return $user;
        }

        // Aus vollem Namen und Jahrgang matchen
        $name = $item['athlete_name'] ?? null;
        if (!$name) return null;

        $parts    = explode(' ', $name, 2);
        $lastname = count($parts) > 1 ? $parts[1] : $parts[0];
        $firstname = count($parts) > 1 ? $parts[0] : null;
        $birthYear = $item['birth_year'] ?? null;

        $query = User::where('lastname', $lastname);
        if ($firstname) $query->where('firstname', $firstname);
        if ($birthYear) $query->whereYear('birth_date', $birthYear);

        return $query->first();
    }

    private function normalizeCourse(?string $course): ?string
    {
        if (!$course) return null;
        if (stripos($course, 'lang') !== false) return 'Langbahn';
        if (stripos($course, 'kurz') !== false) return 'Kurzbahn';
        return $course;
    }

    private function parseDisciplineFromLabel(string $label): ?string
    {
        if (preg_match('/frei|freistil|free|crawl/i', $label))        return 'F';
        if (preg_match('/brust|breaststroke/i', $label))              return 'B';
        if (preg_match('/rücken|back|backstroke/i', $label))          return 'R';
        if (preg_match('/schmetterling|butterfly|delphin/i', $label)) return 'S';
        if (preg_match('/lagen|medley|individual/i', $label))         return 'L';
        return null;
    }

    private function parseDistanceFromLabel(string $label): ?int
    {
        if (preg_match('/(\d+)\s*m/i', $label, $m)) return (int) $m[1];
        return null;
    }
}
