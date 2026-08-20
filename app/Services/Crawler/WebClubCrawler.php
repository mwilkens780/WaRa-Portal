<?php

namespace App\Services\Crawler;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionEvent;
use App\Models\CompetitionResult;
use App\Models\ImportLog;
use App\Models\Season;
use App\Models\Setting;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\TraceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class WebClubCrawler
{
    private const SOURCE = 'webclub_crawler';

    // ── Öffentliche API ──────────────────────────────────────────────────────

    public function run(): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'persons_synced' => 0, 'persons_created' => 0, 'persons_deactivated' => 0];

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

        return $this->processPayload($output);
    }

    /**
     * Verarbeitet den JSON-Output des Playwright-Crawlers und speichert ihn in der DB.
     * Wird von run() und vom GitHub-Actions-API-Endpoint genutzt.
     */
    public function processPayload(array $output): array
    {
        $stats  = ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'groups_synced' => 0, 'results_synced' => 0, 'entries_synced' => 0, 'persons_synced' => 0, 'persons_created' => 0, 'persons_deactivated' => 0];
        $config = $this->buildConfig();

        $stats['groups_synced'] = $this->syncGroups($output['groups'] ?? []);

        // Einmalig alle Schwimmer mit webclub_person_id laden (verhindert N DB-Queries in syncResults)
        $usersByWcId = User::whereNotNull('webclub_person_id')
            ->get(['id', 'webclub_person_id', 'lastname', 'firstname', 'birth_date', 'gender'])
            ->keyBy(fn($u) => (string) $u->webclub_person_id);

        // Per-Wettkampf-Transaktionen statt einer einzigen Riesentransaktion:
        // Verhindert, dass ein Server-Timeout alle bereits gespeicherten Daten wieder löscht.
        foreach ($output['competitions'] ?? [] as $raw) {
            try {
                DB::transaction(function () use ($raw, $config, $usersByWcId, &$stats) {
                    [$status, $resultsSynced, $entriesSynced] = $this->syncCompetition($raw, $config, $usersByWcId);
                    if ($status === 'created' || $status === 'updated') $stats['imported']++;
                    else $stats['skipped']++;
                    $stats['results_synced'] += $resultsSynced;
                    $stats['entries_synced'] += $entriesSynced;
                });
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('WebClubCrawler Wettkampf-Sync: ' . $e->getMessage(), $raw);
            }
        }

        $personStats = $this->syncPersons($output['persons'] ?? []);
        $stats['persons_synced']      = $personStats['synced'];
        $stats['persons_created']     = $personStats['created'] ?? 0;
        $stats['persons_deactivated'] = $personStats['deactivated'] ?? 0;
        $stats['errors']             += $personStats['errors'];

        // Zweiter Pass: syncPersons kann neue Portal-User angelegt haben, die im ersten
        // Durchlauf noch nicht in usersByWcId waren (one-run lag). usersByWcId neu laden
        // und nochmals alle Einträge verarbeiten – existierende werden per Key-Check übersprungen.
        if (($personStats['created'] ?? 0) > 0 && !empty($output['competitions'])) {
            $usersByWcId = User::whereNotNull('webclub_person_id')
                ->get(['id', 'webclub_person_id', 'lastname', 'firstname', 'birth_date', 'gender'])
                ->keyBy(fn($u) => (string) $u->webclub_person_id);

            foreach ($output['competitions'] as $raw) {
                try {
                    DB::transaction(function () use ($raw, $config, $usersByWcId, &$stats) {
                        $competition = null;
                        if (!empty($raw['webclub_id'])) {
                            $competition = Competition::where('webclub_event_id', $raw['webclub_id'])->first();
                        }
                        if (!$competition && !empty($raw['name']) && !empty($raw['date'])) {
                            $competition = Competition::where('name', $raw['name'])
                                ->whereDate('date', $raw['date'])
                                ->first();
                        }
                        if (!$competition) return;

                        $synced = $this->syncEntries($competition, $raw['entries'] ?? [], $usersByWcId, $raw['events'] ?? []);
                        $stats['entries_synced'] += $synced;
                    });
                } catch (\Throwable $e) {
                    Log::error('WebClubCrawler zweiter Pass: ' . $e->getMessage(), $raw);
                }
            }
        }

        foreach ($output['errors'] ?? [] as $err) {
            Log::warning('WebClubCrawler (JS): ' . ($err['type'] ?? '?') . ' – ' . ($err['message'] ?? ''));
            $stats['errors']++;
        }

        return $stats;
    }

    // ── Wettkämpfe ───────────────────────────────────────────────────────────

    private function syncCompetition(array $raw, array $config, \Illuminate\Support\Collection $usersByWcId): array
    {
        $webclubId = $raw['webclub_id'] ?? null;
        $name      = trim($raw['name'] ?? '');
        $date      = $raw['date'] ?? null;

        if (!$name || !$date) {
            return ['skipped', 0];
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
                'date_end'         => $raw['date_end']    ?? null,
                'location'         => $raw['location']    ?? null,
                'course'           => $this->normalizeCourse($raw['course'] ?? null),
                'organizer'        => $raw['organizer']   ?? null,
                'meldeschluss'     => $raw['meldeschluss']?? null,
                'description'      => $raw['description'] ?? null,
                'source_url'       => $raw['webclub_url'] ?? null,
                'webclub_event_id' => $webclubId,
                'season_id'        => $season?->id,
                'type'             => 'regional',
                'venue_details'    => $this->buildVenueDetails($raw),
                'contact_info'     => $this->buildContactInfo($raw),
            ], fn($v) => $v !== null && $v !== ''));

            ImportLog::create([
                'source'         => self::SOURCE,
                'source_url'     => $raw['webclub_url'] ?? null,
                'filename'       => null,
                'status'         => 'success',
                'competition_id' => $competition->id,
                'message'        => 'Wettkampf neu angelegt via WebClub-Crawler.',
            ]);

            $this->syncCompetitionEvents($competition, $raw['events'] ?? [], $raw['sessions'] ?? []);
            $entriesSynced = $this->syncEntries($competition, $raw['entries'] ?? [], $usersByWcId, $raw['events'] ?? []);
            $resultsSynced = $this->syncResults($competition, $raw['results'] ?? [], $usersByWcId, $raw['events'] ?? []);

            TraceService::info("WebClubCrawler: Wettkampf neu angelegt – {$name}", ['id' => $competition->id]);
            return ['created', $resultsSynced, $entriesSynced];
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
        if (empty($competition->venue_details)) {
            $vd = $this->buildVenueDetails($raw);
            if ($vd) $updates['venue_details'] = $vd;
        }
        if (empty($competition->contact_info)) {
            $ci = $this->buildContactInfo($raw);
            if ($ci) $updates['contact_info'] = $ci;
        }

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

        $this->syncCompetitionEvents($competition, $raw['events'] ?? [], $raw['sessions'] ?? []);
        $entriesSynced = $this->syncEntries($competition, $raw['entries'] ?? [], $usersByWcId, $raw['events'] ?? []);
        $resultsSynced = $this->syncResults($competition, $raw['results'] ?? [], $usersByWcId, $raw['events'] ?? []);

        return [$updates ? 'updated' : 'skipped', $resultsSynced, $entriesSynced];
    }

    private function syncEntries(Competition $competition, array $entries, \Illuminate\Support\Collection $usersByWcId, array $webclubEvents = []): int
    {
        if (empty($entries)) return 0;

        // WebClub event_number → discipline+distance (gleiche Logik wie syncResults)
        $wcEventDefs = [];
        foreach ($webclubEvents as $ev) {
            $nr = (int) ($ev['number'] ?? 0);
            if ($nr > 0 && !empty($ev['discipline']) && !empty($ev['distance'])) {
                $wcEventDefs[$nr] = [
                    'discipline' => $ev['discipline'],
                    'distance'   => (int) $ev['distance'],
                    'gender'     => $ev['gender'] ?? 'X',
                ];
            }
        }

        // Bulk: alle Portal-Events dieser Veranstaltung
        $portalEvents     = CompetitionEvent::where('competition_id', $competition->id)->get();
        $portalByDiscDist = $portalEvents->groupBy(fn($e) => $e->discipline . '_' . $e->distance);
        $portalByNumber   = $portalEvents->keyBy('event_number');

        // Bulk-Duplikat-Check (kein N×DB-Query)
        $existingKeys = CompetitionEntry::where('competition_id', $competition->id)
            ->get(['user_id', 'discipline', 'distance'])
            ->mapWithKeys(fn($e) => ["{$e->user_id}_{$e->discipline}_{$e->distance}" => true]);

        $synced = 0;
        foreach ($entries as $entry) {
            if (empty($entry['athlete_name'])) continue;

            $user = $this->findUserFromMap($entry, $usersByWcId);

            // Kein Portal-Account gefunden: Schwimmer aus Meldungen-Daten anlegen (z.B. Bambini mit swrISSWR=0,
            // die nicht in pers.php erscheinen aber aktiv an Wettkämpfen teilnehmen).
            if (!$user) {
                $wcId = $entry['webclub_person_id'] ?? null;
                if (!$wcId) continue;

                $nameParts = preg_split('/\s+/', trim($entry['athlete_name']), -1, PREG_SPLIT_NO_EMPTY);
                $lastname  = array_pop($nameParts) ?: null;
                $firstname = $nameParts ? implode(' ', $nameParts) : null;
                if (!$lastname) continue;

                $birthYear  = $entry['birth_year'] ?? null;
                $initialPwd = Str::random(12);
                $user = User::create(array_filter([
                    'name'              => trim("$firstname $lastname"),
                    'lastname'          => $lastname,
                    'firstname'         => $firstname,
                    'birth_date'        => $birthYear ? "{$birthYear}-01-01" : null,
                    'gender'            => $this->normalizeGender($entry['gender'] ?? null),
                    'role'              => 'schwimmer',
                    'active'            => true,
                    'webclub_person_id' => $wcId,
                    'password'          => $initialPwd,
                    'initial_password'  => $initialPwd,
                ], fn($v) => $v !== null && $v !== ''));

                Log::info("WebClubCrawler syncEntries: Schwimmer aus Meldungen angelegt – {$firstname} {$lastname} (pid={$wcId})");
                $usersByWcId->put((string) $wcId, $user);
            }

            $eventNumber = isset($entry['event_number']) ? (int) $entry['event_number'] : 0;
            $event       = null;
            $discipline  = null;
            $distance    = null;
            $gender      = null;

            if ($eventNumber > 0 && isset($wcEventDefs[$eventNumber])) {
                $def         = $wcEventDefs[$eventNumber];
                $discipline  = $def['discipline'];
                $distance    = $def['distance'];
                $gender      = $def['gender'];
                $discDistKey = $discipline . '_' . $distance;
                $candidates  = $portalByDiscDist->get($discDistKey);
                if ($candidates) {
                    $event = $candidates->count() === 1
                        ? $candidates->first()
                        : ($candidates->firstWhere('gender', $gender)
                            ?? $candidates->firstWhere('gender', 'X')
                            ?? $candidates->first());
                }
            } elseif ($eventNumber > 0 && empty($wcEventDefs)) {
                $event = $portalByNumber->get($eventNumber);
            }

            if (!$discipline && $event) {
                $discipline = $event->discipline;
                $distance   = $event->distance;
                $gender     = $event->gender;
            }

            // Fallback: discipline+distance direkt aus dem XHR (Felder g+l), wcEventDefs nicht nötig
            if (!$discipline && !empty($entry['discipline'])) {
                $discipline  = $entry['discipline'];
                $distance    = (int) ($entry['distance'] ?? 0) ?: null;
                $discDistKey = $discipline . '_' . $distance;
                $candidates  = $portalByDiscDist->get($discDistKey);
                if ($candidates) {
                    $gender = $entry['gender'] ?? null;
                    $event  = $candidates->count() === 1
                        ? $candidates->first()
                        : ($candidates->firstWhere('gender', $gender)
                            ?? $candidates->firstWhere('gender', 'X')
                            ?? $candidates->first());
                }
            }

            if (!$discipline || !$distance) continue;

            $key = "{$user->id}_{$discipline}_{$distance}";
            if (isset($existingKeys[$key])) continue;
            $existingKeys[$key] = true;

            $resolvedGender = ($entry['gender'] ?? $user->gender ?? $gender ?? 'X') ?: 'X';

            CompetitionEntry::create(array_filter([
                'competition_id'       => $competition->id,
                'user_id'              => $user->id,
                'competition_event_id' => $event?->id,
                'discipline'           => $discipline,
                'distance'             => $distance,
                'gender'               => $resolvedGender,
                'entry_time_ms'        => $entry['time_ms'] ?? $entry['entry_time_ms'] ?? null,
                'status'               => 'entered',
            ], fn($v) => $v !== null));
            $synced++;
        }
        return $synced;
    }

    private function syncResults(Competition $competition, array $results, \Illuminate\Support\Collection $usersByWcId, array $webclubEvents = []): int
    {
        if (empty($results)) return 0;

        // WebClub-Eventdefinitionen: event_number → {discipline, distance, gender}
        // Diese kommen aus demselben Crawl-Lauf wie die Ergebnisse und verwenden
        // WebClub's interne Nummerierung. Portal-Events können andere Nummern haben
        // (z.B. aus DSV7-Import), daher darf event_number NICHT direkt im Portal gesucht werden.
        $wcEventDefs = [];
        foreach ($webclubEvents as $ev) {
            $nr = (int) ($ev['number'] ?? 0);
            if ($nr > 0 && !empty($ev['discipline']) && !empty($ev['distance'])) {
                $wcEventDefs[$nr] = [
                    'discipline' => $ev['discipline'],
                    'distance'   => (int) $ev['distance'],
                    'gender'     => $ev['gender'] ?? 'X',
                ];
            }
        }

        // Bulk: alle Portal-Events dieser Veranstaltung (1 Query)
        $portalEvents   = CompetitionEvent::where('competition_id', $competition->id)->get();
        // Gruppiert nach Disziplin+Distanz für systemübergreifendes Matching
        $portalByDiscDist = $portalEvents->groupBy(fn($e) => $e->discipline . '_' . $e->distance);
        // Als Fallback: direkt per event_number (nur korrekt wenn Portal-Events WebClub-Nummern haben)
        $portalByNumber = $portalEvents->keyBy('event_number');

        // Bulk: bereits vorhandene Ergebnisse (1 Query statt N exists()-Queries)
        $existingKeys = CompetitionResult::where('competition_id', $competition->id)
            ->get(['user_id', 'discipline', 'distance'])
            ->mapWithKeys(fn($r) => ["{$r->user_id}_{$r->discipline}_{$r->distance}" => true]);

        $synced = 0;
        foreach ($results as $result) {
            if (empty($result['athlete_name']) || empty($result['time_ms'])) continue;

            $user = $this->findUserFromMap($result, $usersByWcId);
            if (!$user) continue;

            $eventNumber = isset($result['event_number']) ? (int) $result['event_number'] : 0;
            $event       = null;

            if ($eventNumber > 0 && isset($wcEventDefs[$eventNumber])) {
                // Korrekte Methode: WebClub-Nummer → Disziplin+Distanz → Portal-Event
                $def        = $wcEventDefs[$eventNumber];
                $discDistKey = $def['discipline'] . '_' . $def['distance'];
                $candidates = $portalByDiscDist->get($discDistKey);
                if ($candidates) {
                    if ($candidates->count() === 1) {
                        $event = $candidates->first();
                    } else {
                        // Mehrere Events mit gleicher Disziplin+Distanz: per Geschlecht disambiguieren
                        $gender = $result['gender'] ?? null;
                        $event  = $gender
                            ? ($candidates->firstWhere('gender', $gender)
                                ?? $candidates->firstWhere('gender', 'X')
                                ?? $candidates->first())
                            : $candidates->first();
                    }
                }
            } elseif ($eventNumber > 0 && empty($wcEventDefs)) {
                // Fallback nur wenn WebClub keine Eventdefinitionen geliefert hat:
                // Direktes Nachschlagen per event_number (korrekt wenn Portal-Events WebClub-Nummern haben)
                $event = $portalByNumber->get($eventNumber);
            }

            if (!$event) continue;

            // Duplikat-Check aus In-Memory-Cache (kein DB-Query)
            $key = "{$user->id}_{$event->discipline}_{$event->distance}";
            if (isset($existingKeys[$key])) continue;
            $existingKeys[$key] = true;

            $wcRek = trim((string) ($result['webclub_rek'] ?? ''));
            CompetitionResult::create(array_filter([
                'competition_id' => $competition->id,
                'user_id'        => $user->id,
                'discipline'     => $event->discipline,
                'distance'       => $event->distance,
                'gender'         => $result['gender'] ?? $event->gender ?? null,
                'time_ms'        => (int) $result['time_ms'],
                'placement'      => $result['placement'] ?? null,
                'age_group'      => $event->age_group ?? null,
                'webclub_rek'    => $wcRek ?: null,
            ]));
            $synced++;
        }
        return $synced;
    }

    private function syncCompetitionEvents(Competition $competition, array $events, array $sessions): void
    {
        if (empty($events)) return;

        // DSV7-Import hat Vorrang: wenn bereits Events existieren, überspringen
        if ($competition->events()->exists()) return;

        // Session-Nr → date/name Index
        $sessionMeta = [];
        foreach ($sessions as $s) {
            $nr = (int) ($s['number'] ?? 0);
            if ($nr > 0) {
                $sessionMeta[$nr] = [
                    'date' => $s['date'] ?? null,
                    'name' => $s['name'] ?? null,
                    'time' => $s['time'] ?? null,
                ];
            }
        }

        foreach ($events as $ev) {
            if (empty($ev['discipline']) || empty($ev['distance'])) continue;
            // Unbekannte Disziplincodes (z.B. 14 = Bambini "Kindgerecht") überspringen
            if (!in_array($ev['discipline'], ['F', 'B', 'R', 'S', 'L'])) continue;

            $sessionNr = max(1, (int) ($ev['session'] ?? 1));
            $meta      = $sessionMeta[$sessionNr] ?? [];

            $evNr = (int) ($ev['number'] ?? 0);
            if (CompetitionEvent::where('competition_id', $competition->id)
                    ->where('event_number', $evNr)->exists()) {
                continue;
            }

            CompetitionEvent::create([
                'competition_id'     => $competition->id,
                'event_number'       => $evNr,
                'session_number'     => $sessionNr,
                'session_date'       => $meta['date'] ?? null,
                'session_name'       => $meta['name'] ?? null,
                'discipline'         => $ev['discipline'],
                'distance'           => (int) $ev['distance'],
                'gender'             => $ev['gender'] ?? 'X',
                'age_group'          => $ev['age_group'] ?? null,
                'qualifying_time_ms' => $ev['qualifying_time_ms'] ?? null,
            ]);
        }
    }

    private function findOrSkipEvent(Competition $competition, array $item): ?CompetitionEvent
    {
        // Direkt per Event-Nummer (aus WebClub-XHR: ergWKFNR → event_number)
        $eventNumber = isset($item['event_number']) ? (int) $item['event_number'] : 0;
        if ($eventNumber > 0) {
            $event = CompetitionEvent::where('competition_id', $competition->id)
                ->where('event_number', $eventNumber)
                ->first();
            if ($event) return $event;
        }

        // Fallback: Disziplin + Distanz aus Label parsen (DOM-Scraper / DSV7-Import)
        $eventLabel = $item['event_label'] ?? null;
        if (!$eventLabel) return null;

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
        $synced     = 0;
        $created    = 0;
        $errors     = 0;
        $webclubIds = [];

        // Bulk-Preloads: 1 Query statt N Queries pro Person.
        $usersByWcId = User::whereNotNull('webclub_person_id')
            ->get()
            ->keyBy(fn($u) => (string) $u->webclub_person_id);

        $groupsByWcId = TrainingGroup::whereNotNull('webclub_id')
            ->get()
            ->keyBy(fn($g) => (string) $g->webclub_id);

        // Bestehende Gruppienzuordnungen: user_id → [group_id, ...]
        $existingMemberships = DB::table('training_group_swimmer')
            ->select('user_id', 'training_group_id')
            ->get()
            ->groupBy('user_id')
            ->map(fn($rows) => $rows->pluck('training_group_id')->all());

        foreach ($persons as $raw) {
            try {
                $result = $this->syncPerson($raw, $usersByWcId, $groupsByWcId, $existingMemberships);
                if ($result === 'created') { $created++; $synced++; }
                elseif ($result === 'synced') $synced++;

                $wcId = $raw['webclub_person_id'] ?? null;
                if ($wcId) $webclubIds[] = (string) $wcId;
            } catch (\Throwable $e) {
                $errors++;
                Log::error('WebClubCrawler Personen-Sync: ' . $e->getMessage(), $raw);
            }
        }

        // Schwimmer deaktivieren, die nicht mehr in WebClub vorhanden sind.
        // Mindestanzahl 5 als Schutz gegen versehentliche Massendeaktivierung bei leerem Crawl.
        $deactivated = 0;
        if (count($webclubIds) >= 5) {
            $deactivated = $this->deactivateAbsentPersons($webclubIds);
            if ($deactivated > 0) {
                Log::info("WebClubCrawler: {$deactivated} Schwimmer deaktiviert (nicht mehr in WebClub).");
            }
        }

        if ($synced > 0 || $errors > 0 || $deactivated > 0) {
            ImportLog::create([
                'source'  => self::SOURCE,
                'status'  => $errors > 0 ? 'error' : 'success',
                'message' => "Personen-Sync: {$synced} bearbeitet ({$created} neu angelegt), {$deactivated} deaktiviert, {$errors} Fehler.",
            ]);
        }

        return compact('synced', 'created', 'errors', 'deactivated');
    }

    private function syncPerson(array $raw, \Illuminate\Support\Collection $usersByWcId, \Illuminate\Support\Collection $groupsByWcId, \Illuminate\Support\Collection &$existingMemberships): string
    {
        $webclubId = $raw['webclub_person_id'] ?? null;
        $lastname  = trim($raw['lastname']  ?? '');
        $firstname = trim($raw['firstname'] ?? '');
        $birthDate = $raw['birth_date'] ?? null;

        if (!$lastname && !$firstname) return 'skipped';

        // Erst per webclub_id aus dem Preload-Map (kein DB-Query)
        $user = $webclubId ? $usersByWcId->get((string) $webclubId) : null;

        // Fallback: Name+Geburtsdatum (weiterhin per DB, da kein sinnvoller Bulk-Preload möglich)
        if (!$user && $lastname && $birthDate) {
            $user = User::where('lastname', $lastname)
                ->where('firstname', $firstname)
                ->where('birth_date', $birthDate)
                ->first();
        }
        if (!$user && $lastname && $firstname) {
            $user = User::where('lastname', $lastname)
                ->where('firstname', $firstname)
                ->whereNull('birth_date')
                ->first();
        }

        if (!$user) {
            $email      = !empty($raw['email']) ? $raw['email'] : null;
            $initialPwd = Str::random(12);
            $user = User::create(array_filter([
                'name'              => trim("$firstname $lastname"),
                'lastname'          => $lastname,
                'firstname'         => $firstname,
                'email'             => $email,
                'password'          => $initialPwd,
                'initial_password'  => $initialPwd,
                'birth_date'        => $birthDate,
                'gender'            => $this->normalizeGender($raw['gender'] ?? null),
                'role'              => 'schwimmer',
                'active'            => true,
                'webclub_person_id' => $webclubId,
                'membership_number' => !empty($raw['membership_number']) ? $raw['membership_number'] : null,
                'dsv_id'            => !empty($raw['dsv_id']) ? $raw['dsv_id'] : null,
            ], fn($v) => $v !== null && $v !== ''));

            $this->syncGroupMembership($user, $raw['webclub_group_ids'] ?? [], $groupsByWcId, $existingMemberships);

            Log::info("WebClubCrawler: Neuer Schwimmer angelegt – {$firstname} {$lastname}");
            return 'created';
        }

        $updates = [];
        if (!$user->webclub_person_id && $webclubId)                                $updates['webclub_person_id'] = $webclubId;
        $mappedGender = $this->normalizeGender($raw['gender'] ?? null);
        if (empty($user->gender)            && $mappedGender)                       $updates['gender']            = $mappedGender;
        if (empty($user->dsv_id)            && !empty($raw['dsv_id']))              $updates['dsv_id']            = $raw['dsv_id'];
        if (empty($user->membership_number) && !empty($raw['membership_number']))   $updates['membership_number'] = $raw['membership_number'];
        if (empty($user->member_since)      && !empty($raw['member_since']))        $updates['member_since']      = $raw['member_since'];
        if (empty($user->phone)             && !empty($raw['phone']))               $updates['phone']             = $raw['phone'];
        if (empty($user->mobile)            && !empty($raw['mobile']))              $updates['mobile']            = $raw['mobile'];
        if (empty($user->street)            && !empty($raw['street']))              $updates['street']            = $raw['street'];
        if (empty($user->postal_code)       && !empty($raw['postal_code']))         $updates['postal_code']       = $raw['postal_code'];
        if (empty($user->city)              && !empty($raw['city']))                $updates['city']              = $raw['city'];
        if (!$user->active)                                                         $updates['active']            = true;

        if ($updates) $user->update($updates);

        $this->syncGroupMembership($user, $raw['webclub_group_ids'] ?? [], $groupsByWcId, $existingMemberships);

        return $updates ? 'synced' : 'skipped';
    }

    private function syncGroups(array $rawGroups): int
    {
        $synced = 0;
        foreach ($rawGroups as $raw) {
            $wcId = trim((string) ($raw['webclub_id'] ?? ''));
            $name = trim((string) ($raw['name']       ?? ''));
            if (!$wcId || !$name) continue;

            // Suche portal-Gruppe per Name (case-insensitive, dann exakt).
            // Setzt webclub_id wenn noch nicht gesetzt oder abweichend.
            $group = TrainingGroup::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                  ?? TrainingGroup::where('name', $name)->first();

            if (!$group) {
                Log::info("WebClubCrawler Gruppen: '{$name}' (ID {$wcId}) nicht im Portal – übersprungen.");
                continue;
            }

            if ((string) $group->webclub_id !== $wcId) {
                $group->update(['webclub_id' => $wcId]);
                Log::info("WebClubCrawler Gruppen: '{$name}' → webclub_id={$wcId} gesetzt.");
                $synced++;
            }
        }
        return $synced;
    }

    private function syncGroupMembership(User $user, array $webclubGroupIds, \Illuminate\Support\Collection $groupsByWcId, \Illuminate\Support\Collection &$existingMemberships): void
    {
        if (empty($webclubGroupIds)) return;

        foreach ($webclubGroupIds as $wcId) {
            $wcId = (string) $wcId;
            if ($wcId === '') continue;

            // Lookup aus Preload-Map (kein DB-Query)
            $group = $groupsByWcId->get($wcId);
            if (!$group) {
                Log::warning("WebClubCrawler: Keine Trainingsgruppe für WebClub-ID {$wcId} – webclub_id in Trainingsgruppen pflegen.");
                continue;
            }

            // Membership-Check aus In-Memory-Cache (kein DB-Query)
            $userGroups = $existingMemberships->get($user->id, []);
            if (!in_array($group->id, $userGroups)) {
                $user->trainingGroups()->attach($group->id);
                // Cache aktualisieren damit Doppelanlagen vermieden werden
                $existingMemberships->put($user->id, array_merge($userGroups, [$group->id]));
            }
        }
    }

    private function deactivateAbsentPersons(array $webclubIds): int
    {
        if (empty($webclubIds)) return 0;

        // Schwimmer mit aktuellen Wettkampf-Meldungen schützen: sie sind aktiv,
        // auch wenn sie nicht in pers.php erscheinen (z.B. swrISSWR=0, Bambini).
        $protectedIds = DB::table('competition_entries')
            ->join('competitions', 'competition_entries.competition_id', '=', 'competitions.id')
            ->where('competitions.date', '>=', now()->subMonths(18))
            ->distinct()
            ->pluck('competition_entries.user_id')
            ->toArray();

        // Bereits fälschlicherweise deaktivierte Schwimmer mit aktuellen Meldungen reaktivieren.
        if (!empty($protectedIds)) {
            $reactivated = User::where('role', 'schwimmer')
                ->whereNotNull('webclub_person_id')
                ->whereNotIn('webclub_person_id', $webclubIds)
                ->where('active', false)
                ->whereIn('id', $protectedIds)
                ->update(['active' => true]);
            if ($reactivated > 0) {
                Log::info("WebClubCrawler: {$reactivated} Schwimmer reaktiviert (haben aktuelle Meldungen, waren fälschlicherweise deaktiviert).");
            }
        }

        // Deaktivieren: fehlt in pers.php UND hat keine aktuellen Meldungen.
        $deactivated = User::where('role', 'schwimmer')
            ->whereNotNull('webclub_person_id')
            ->whereNotIn('webclub_person_id', $webclubIds)
            ->whereNotIn('id', $protectedIds)
            ->where('active', true)
            ->update(['active' => false]);

        return $deactivated;
    }

    private function normalizeGender(?string $gender): ?string
    {
        if (!$gender) return null;
        $g = strtoupper(trim($gender));
        if ($g === 'W') return 'F';
        if (in_array($g, ['M', 'F', 'X'])) return $g;
        return null;
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
        $dsvId     = $item['dsv_id'] ?? null;

        if ($webclubId) {
            $user = User::where('webclub_person_id', $webclubId)->first();
            if ($user) return $user;
        }

        // Fallback: DSV-ID (d-Feld aus Meldungen-XHR / swrDSVID aus pers.php)
        if ($dsvId) {
            $user = User::where('dsv_id', $dsvId)->first();
            if ($user) {
                // webclub_person_id nachträglich setzen, damit future Läufe den Map-Pfad nutzen
                if (!$user->webclub_person_id && $webclubId) {
                    $user->update(['webclub_person_id' => $webclubId]);
                }
                return $user;
            }
        }

        // Aus vollem Namen (letztes Wort = Nachname) und Jahrgang matchen
        $name = $item['athlete_name'] ?? null;
        if (!$name) return null;

        $nameParts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $lastname  = array_pop($nameParts) ?: null;
        $firstname = $nameParts ? implode(' ', $nameParts) : null;
        $birthYear = $item['birth_year'] ?? null;

        if (!$lastname) return null;

        $query = User::where('lastname', $lastname);
        if ($firstname) $query->where('firstname', $firstname);
        if ($birthYear) $query->whereYear('birth_date', $birthYear);

        $user = $query->first();
        if ($user && $webclubId && !$user->webclub_person_id) {
            $user->update(['webclub_person_id' => $webclubId]);
        }
        return $user;
    }

    private function findUserFromMap(array $item, \Illuminate\Support\Collection $usersByWcId): ?User
    {
        $webclubId = $item['webclub_person_id'] ?? null;
        if ($webclubId && ($user = $usersByWcId->get((string) $webclubId))) {
            return $user;
        }
        // Fallback: DB-Query per Name (für Einträge ohne webclub_person_id)
        return $this->findUser($item);
    }

    private function buildVenueDetails(array $raw): ?array
    {
        $details = array_filter([
            'name'        => $raw['venue_name']   ?? null,
            'street'      => $raw['venue_street'] ?? null,
            'postal_code' => $raw['venue_postal'] ?? null,
            'city'        => $raw['venue_city']   ?? null,
            'zeitnahme'   => $raw['zeitnahme']    ?? null,
        ]);
        return $details ?: null;
    }

    private function buildContactInfo(array $raw): ?array
    {
        $info = array_filter([
            'veranstalter'      => $raw['veranstalter']      ?? null,
            'name'              => $raw['contact_name']      ?? null,
            'email'             => $raw['contact_email']     ?? null,
            'melde_name'        => $raw['melde_name']        ?? null,
            'melde_email'       => $raw['melde_email']       ?? null,
            'melde_phone'       => $raw['melde_phone']       ?? null,
            'meldeschluss_time' => $raw['meldeschluss_time'] ?? null,
        ]);
        return $info ?: null;
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
