<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\HallBooking;
use App\Models\HallResource;
use App\Models\Record;
use App\Models\Season;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Feste Testdaten fuer die Browsertests (tests/e2e, GitHub Action "E2E").
 *
 * Nur fuer eine frische, leere Test-Datenbank gedacht:
 *   php artisan migrate:fresh --seed --seeder=E2eSeeder
 *
 * Konten (Passwort ueberall "E2e-Test-2026"):
 *   admin@e2e.test, trainer@e2e.test, schwimmer@e2e.test, eltern@e2e.test
 * Keins hat ein Startpasswort - sonst leitet jede Seite auf "Passwort aendern".
 */
class E2eSeeder extends Seeder
{
    public const PASSWORD = 'E2e-Test-2026';

    public function run(): void
    {
        $this->call(MenuPermissionSeeder::class);

        $season = Season::create([
            'name'       => now()->month >= 8 ? now()->year . '/' . (now()->year + 1 - 2000) : (now()->year - 1) . '/' . (now()->year - 2000),
            'start_date' => now()->month >= 8 ? now()->startOfYear()->setMonth(8)->startOfMonth() : now()->subYear()->startOfYear()->setMonth(8)->startOfMonth(),
            'end_date'   => now()->month >= 8 ? now()->addYear()->startOfYear()->setMonth(7)->endOfMonth() : now()->startOfYear()->setMonth(7)->endOfMonth(),
            'is_current' => true,
        ]);

        $user = fn(string $role, string $first, string $last, array $extra = []) => User::create(array_merge([
            'name'                => "$first $last",
            'firstname'           => $first,
            'lastname'            => $last,
            'email'               => Str::lower(Str::ascii($role)) . '@e2e.test',
            'password'            => Hash::make(self::PASSWORD),
            'role'                => $role,
            'active'              => true,
            'portal_active'       => true,
        ], $extra));

        $admin   = $user('admin', 'Ada', 'Admin');
        $trainer = $user('trainer', 'Tom', 'Trainer', ['phone' => '040-1234567']);
        $swimmer = $user('schwimmer', 'Sina', 'Schwimmer', ['birth_date' => now()->subYears(13)->format('Y-m-d'), 'gender' => 'F']);
        $second  = $user('schwimmer', 'Ben', 'Bahn', ['email' => 'ben@e2e.test', 'birth_date' => now()->subYears(15)->format('Y-m-d'), 'gender' => 'M']);
        // Drittes Geschlecht: Rekord-/Bestenlisten-Filter "Divers" und Anzeigen
        $diverse = $user('schwimmer', 'Dani', 'Delfin', ['email' => 'dani@e2e.test', 'birth_date' => now()->subYears(14)->format('Y-m-d'), 'gender' => 'D']);
        // Vorstand (Sitzungen) und Kampfrichter (Kampfrichter-Abfrage)
        $board   = $user('vorstand', 'Vera', 'Vorstand');
        // Lizenz läuft in 3 Monaten aus (Erinnerung im Dashboard, Liste des Vorstands)
        $official = $user('kampfrichter', 'Kai', 'Kampfrichter', ['kampfrichter_license_nr' => 'KR-4711', 'kampfrichter_license_valid_until' => now()->addMonths(3)->format('Y-m-d')]);
        // Geschäftsstelle: Benutzer, Gruppen, Termine, Lizenzen
        $office  = $user('geschaeftsstelle', 'Gina', 'Geschäftsstelle');
        $parent  = $user('elternteil', 'Elke', 'Eltern', ['email' => 'eltern@e2e.test', 'mobile' => '0170 1234567', 'carpool_share_phone' => true]);
        $parent->children()->attach($swimmer->id);

        foreach ([$admin, $trainer, $swimmer, $second, $diverse, $board, $official, $office, $parent] as $u) {
            $u->forceFill(['portal_activated_at' => now()])->saveQuietly();
        }

        $group = TrainingGroup::create(['name' => 'E2E-Gruppe', 'color' => 'blue', 'group_type' => 'leistungssport', 'active' => true]);
        $group->trainers()->attach($trainer->id);
        $group->swimmers()->attach([$swimmer->id, $second->id, $diverse->id]);

        // Einheiten: vergangene, heutige Woche, eine Serie in der Zukunft
        $serie = (string) Str::uuid();
        // Vergangene Einzeltermine und eine echte Wochenserie (gleicher Wochentag)
        foreach ([-14, -7, -2, 2, 9, 16] as $i => $tage) {
            $s = TrainingSession::create([
                'title'      => $tage < 0 ? 'Techniktraining' : 'Frühtraining',
                'date'       => now()->addDays($tage)->format('Y-m-d'),
                'start_time' => '06:00',
                'end_time'   => '07:30',
                'location'   => 'Stadtbad Norderstedt',
                'type'       => $tage < 0 ? 'technik' : 'ausdauer',
                'recurrence_type'     => $tage > 0 ? 'weekly' : 'none',
                'recurrence_group_id' => $tage > 0 ? $serie : null,
            ]);
            $s->trainingGroups()->attach($group->id);
            $s->coTrainers()->attach($trainer->id);
        }

        // Hallenplan
        // Die Bahnen legt bereits eine Migration an - nur ergaenzen, falls sie fehlen
        $bahnen = [];
        foreach (['Bahn 1' => '#3B82F6', 'Bahn 2' => '#10B981'] as $name => $farbe) {
            $bahnen[] = HallResource::firstOrCreate(['name' => $name], ['type' => 'lane', 'color' => $farbe, 'sort_order' => count($bahnen) + 1, 'active' => true]);
        }
        HallBooking::create([
            'hall_resource_id' => $bahnen[0]->id, 'day_of_week' => 1, 'start_time' => '17:00', 'end_time' => '18:30',
            'label' => 'E2E-Gruppe', 'type' => 'training', 'training_group_id' => $group->id, 'trainer_id' => $trainer->id,
            'created_by_id' => $admin->id,
        ]);
        HallBooking::create([
            'hall_resource_id' => $bahnen[1]->id, 'day_of_week' => 3, 'start_time' => '18:00', 'end_time' => '19:00',
            'label' => 'Kurs Seepferdchen', 'type' => 'course', 'created_by_id' => $admin->id,
            'trainer_id' => $trainer->id, 'notes' => "Eltern bitte am Beckenrand warten.\nSchwimmbrillen mitbringen.",
        ]);

        // Wettkaempfe: vergangen (mit Ergebnis) und kommend (fuer Zusammenfuehren im DSV-Import)
        $vergangen = Competition::create([
            'name' => 'E2E-Vereinsmeisterschaft', 'location' => 'Norderstedt', 'date' => now()->subDays(20)->format('Y-m-d'),
            'type' => 'vereinsintern', 'course' => 'Kurzbahn', 'season_id' => $season->id,
        ]);
        $vergangen->trainingGroups()->attach($group->id);
        CompetitionResult::create([
            'competition_id' => $vergangen->id, 'user_id' => $swimmer->id, 'discipline' => 'F', 'distance' => 100,
            'time_ms' => 72340, 'placement' => 2, 'is_personal_best' => true,
        ]);
        // Übungsform: markiert, keine Bestzeit
        CompetitionResult::create([
            'competition_id' => $vergangen->id, 'user_id' => $swimmer->id, 'discipline' => 'S', 'distance' => 25,
            'exercise' => 'KB', 'time_ms' => 21500, 'placement' => 1, 'is_personal_best' => false, 'gender' => 'F',
        ]);
        CompetitionResult::create([
            'competition_id' => $vergangen->id, 'user_id' => $diverse->id, 'discipline' => 'F', 'distance' => 50,
            'time_ms' => 34120, 'placement' => 1, 'is_personal_best' => true, 'gender' => 'D',
        ]);
        // Langbahn-Ergebnis: Rudolph-Punkte in "Meine Zeiten"
        $langbahn = Competition::create([
            'name' => 'E2E-Langbahnmeeting', 'location' => 'Hamburg', 'date' => now()->subDays(40)->format('Y-m-d'),
            'type' => 'regional', 'course' => 'Langbahn', 'season_id' => $season->id,
        ]);
        CompetitionResult::create([
            'competition_id' => $langbahn->id, 'user_id' => $swimmer->id, 'discipline' => 'F', 'distance' => 100,
            'time_ms' => 68000, 'placement' => 3, 'is_personal_best' => true, 'gender' => 'F',
        ]);
        $kommend = Competition::create([
            'name' => 'E2E-Sprintpokal', 'location' => 'Kiel', 'date' => now()->addDays(10)->format('Y-m-d'),
            'type' => 'regional', 'course' => 'Kurzbahn', 'season_id' => $season->id,
        ]);
        $kommend->trainingGroups()->attach($group->id);
        // Anmeldeabfrage mit Vereinsbus und Fahrgemeinschaft (Elke bietet 2 Plaetze an, Sina faehrt selbst mit)
        $abfrage = \App\Models\CompetitionSignupRequest::create([
            'competition_id' => $kommend->id, 'status' => 'active', 'deadline' => now()->addDays(7)->format('Y-m-d'),
            'eligible_group_ids' => [$group->id], 'created_by_id' => $admin->id, 'activated_at' => now(),
            'meeting_point' => 'Stadtbad Norderstedt', 'meeting_time' => '07:00', 'bus_available' => true, 'bus_seats' => 8,
        ]);
        \App\Models\CompetitionSignupResponse::create([
            'competition_signup_request_id' => $abfrage->id, 'user_id' => $swimmer->id, 'status' => 'attending', 'responded_at' => now(),
            'carpool_seats' => 2, 'carpool_offered_by_id' => $parent->id, 'carpool_note' => 'Abfahrt 7:15 am Stadtbad', 'carpool_show_phone' => true,
        ]);
        \App\Models\CompetitionSignupResponse::create([
            'competition_signup_request_id' => $abfrage->id, 'user_id' => $second->id, 'status' => 'attending', 'responded_at' => now(),
        ]);

        // Lange Namen wie in echt: bringen Ueberlaeufe auf dem Handy ans Licht
        // (Befund 01.10.2026: abgeschnittener Name machte das Dashboard breiter als den Bildschirm)
        $lang = Competition::create([
            'name' => 'Internationale Deutsche Kurzbahnmeisterschaften der Masters und Offenen Klasse Wuppertal', 'location' => 'Schwimmoper Wuppertal',
            'date' => now()->addDays(25)->format('Y-m-d'), 'type' => 'national', 'course' => 'Kurzbahn', 'season_id' => $season->id,
        ]);
        $lang->trainingGroups()->attach($group->id);
        \App\Models\SwimmingTime::create([
            'user_id' => $swimmer->id, 'training_session_id' => TrainingSession::where('title', 'Techniktraining')->value('id'),
            'discipline' => 'F', 'distance' => 100, 'time_ms' => 75120,
        ]);

        CalendarEvent::create([
            'title' => 'E2E-Sommerfest', 'start_date' => now()->addDays(5)->format('Y-m-d'), 'start_time' => '15:00',
            'type' => 'vereinstermin', 'season_id' => $season->id, 'created_by' => $admin->id,
        ]);

        // Termine mit Einladung: Vorstandssitzung (mit Gast per Link und früherem Protokoll),
        // Elternabend und Trainingslager (Elke sagt für Sina zu)
        $frueher = CalendarEvent::create([
            'title' => 'E2E-Vorstandssitzung August', 'start_date' => now()->subDays(30)->format('Y-m-d'), 'start_time' => '19:00',
            'type' => 'vorstandssitzung', 'created_by' => $board->id, 'rsvp_enabled' => true,
        ]);
        $frueher->invitees()->create(['user_id' => $board->id, 'source' => 'vorstand', 'status' => 'zugesagt', 'invited_at' => now()]);
        $frueher->files()->create(['category' => 'protokoll', 'title' => 'Protokoll August', 'url' => 'https://example.org/protokoll-august.pdf', 'created_by_id' => $board->id]);
        $sitzung = CalendarEvent::create([
            'title' => 'E2E-Vorstandssitzung', 'start_date' => now()->addDays(6)->format('Y-m-d'), 'start_time' => '19:30', 'end_time' => '21:30',
            'type' => 'vorstandssitzung', 'location' => 'Vereinsheim, Raum 2', 'created_by' => $board->id,
            'agenda' => '<ol><li>Begrüßung</li><li>Bericht der Kasse</li><li>Planung Trainingslager</li></ol>',
            'rsvp_enabled' => true, 'rsvp_deadline' => now()->addDays(4)->format('Y-m-d'),
        ]);
        $sitzung->invitees()->create(['user_id' => $board->id, 'source' => 'vorstand', 'invited_at' => now()]);
        $sitzung->invitees()->create(['user_id' => $admin->id, 'source' => 'gast', 'status' => 'zugesagt', 'comment' => 'Komme etwas später', 'invited_at' => now()]);
        $sitzung->invitees()->create(['guest_name' => 'Gerd Gast', 'guest_email' => 'gast@example.org', 'source' => 'gast',
            'token' => 'E2E-GAST-TOKEN-0123456789abcdef0123456789abcdef', 'invited_at' => now()]);
        $sitzung->files()->create(['category' => 'protokoll', 'title' => 'Protokoll August (' . $frueher->start_date->format('d.m.Y') . ')',
            'source_file_id' => $frueher->files()->value('id'), 'created_by_id' => $board->id]);
        $sitzung->files()->create(['category' => 'agenda', 'title' => 'Kassenbericht (Entwurf)', 'url' => 'https://example.org/kasse', 'created_by_id' => $board->id]);

        $elternabend = CalendarEvent::create([
            'title' => 'E2E-Elternabend', 'start_date' => now()->addDays(8)->format('Y-m-d'), 'start_time' => '18:30',
            'type' => 'elternabend', 'location' => 'Stadtbad, Seminarraum', 'created_by' => $trainer->id, 'rsvp_enabled' => true,
        ]);
        $elternabend->invitees()->create(['user_id' => $parent->id, 'source' => 'eltern', 'invited_at' => now()]);

        $lager = CalendarEvent::create([
            'title' => 'E2E-Trainingslager', 'start_date' => now()->addDays(20)->format('Y-m-d'), 'end_date' => now()->addDays(23)->format('Y-m-d'),
            'type' => 'team_event', 'location' => 'Sportschule Malente', 'created_by' => $trainer->id,
            'rsvp_enabled' => true, 'rsvp_deadline' => now()->addDays(10)->format('Y-m-d'), 'capacity' => 20,
            'description' => 'Vier Tage Training im Langbahnbecken, Unterkunft im Mehrbettzimmer.',
        ]);
        foreach ([$swimmer, $second] as $m) {
            $lager->invitees()->create(['user_id' => $m->id, 'source' => 'gruppe', 'invited_at' => now()]);
        }

        // Kampfrichter-Abfrage zum Sprintpokal: Kai hat geantwortet, Vera noch nicht
        $kr = \App\Models\CompetitionOfficialRequest::create([
            'competition_id' => $kommend->id, 'created_by_id' => $board->id,
            'message' => 'Wir stellen vier Kampfrichter je Abschnitt.', 'deadline' => now()->addDays(7)->format('Y-m-d'),
        ]);
        $kr->invitees()->create([
            'user_id' => $official->id, 'invited_at' => now(), 'responded_at' => now(),
            'availability' => [$kommend->date->format('Y-m-d') => ['available' => true, 'comment' => 'ab 9 Uhr']],
            'positions' => ['ZN', 'WR'], 'comment' => 'Lizenz C',
        ]);
        $kr->invitees()->create(['user_id' => $board->id, 'invited_at' => now()]);
        // Bedarf: zwei Zeitnehmer und ein Schiedsrichter im Abschnitt 1
        $kr->needs()->create(['session_number' => 1, 'position' => 'ZN', 'count' => 2]);
        $kr->needs()->create(['session_number' => 1, 'position' => 'SCH', 'count' => 1]);
        // Weitere Qualifikation von Kai (Hauptlizenz entsteht aus den Lizenzfeldern)
        \App\Models\OfficialQualification::create(['user_id' => $official->id, 'title' => 'Starter*in', 'acquired_on' => now()->subYears(2)->format('Y-m-d'), 'valid_until' => now()->addYears(2)->format('Y-m-d')]);
        // Vergangener Einsatz: gemeldet als Zeitnehmer ("Letzte Einsätze")
        $krAlt = \App\Models\CompetitionOfficialRequest::create([
            'competition_id' => $vergangen->id, 'created_by_id' => $board->id, 'closed_at' => now()->subDays(25), 'finalized_at' => now()->subDays(25), 'finalized_by_id' => $board->id,
        ]);
        $krAltInv = $krAlt->invitees()->create([
            'user_id' => $official->id, 'invited_at' => now()->subDays(30), 'responded_at' => now()->subDays(28), 'kari_group' => 'WKR',
            'availability' => [$vergangen->date->format('Y-m-d') => ['available' => true, 'comment' => null]], 'positions' => ['ZN'],
        ]);
        $krAltInv->assignments()->create(['session_number' => 1, 'position' => 'ZN']);
        // Abgelaufene Lizenz eines Elternteils (Liste des Vorstands)
        $parent->forceFill(['kampfrichter_license_nr' => 'KR-0815', 'kampfrichter_license_valid_until' => now()->subMonths(2)->format('Y-m-d')])->save();

        // DMS-J: Mannschaftswertung aus fünf Staffeln je Mannschaft (Reiter "Mannschaftswertung")
        $dms = Competition::create([
            'name' => 'E2E-DMS-J Landesentscheid', 'location' => 'Niebüll', 'date' => now()->subDays(3)->format('Y-m-d'),
            'type' => 'dms', 'course' => 'Kurzbahn', 'season_id' => $season->id,
        ]);
        foreach (['F' => [300000, 310000], 'B' => [360000, 350000], 'R' => [330000, 340000], 'S' => [320000, 0], 'L' => [325000, 335000]] as $disc => [$own, $other]) {
            \App\Models\RelayResult::create(['competition_id' => $dms->id, 'discipline' => $disc, 'distance' => 100, 'relay_legs' => 4,
                'club_name' => 'SG Wasserratten Norderstedt', 'team_number' => 1, 'round' => 'E', 'time_ms' => $own, 'status' => 'OK', 'age_group' => 'Jugend D', 'gender' => 'F']);
            \App\Models\RelayResult::create(['competition_id' => $dms->id, 'discipline' => $disc, 'distance' => 100, 'relay_legs' => 4,
                'club_name' => 'SG Lübeck', 'team_number' => 1, 'round' => 'E', 'time_ms' => $other ?: null, 'status' => $other ? 'OK' : 'DQ', 'age_group' => 'Jugend D', 'gender' => 'F']);
        }

        Record::create([
            'type' => 'vereinsrekord', 'discipline' => 'F', 'distance' => 50, 'gender' => 'M', 'course' => 'Langbahn',
            'swimmer_name' => 'Ben Bahn', 'time_ms' => 24530, 'set_year' => now()->year - 1, 'location' => 'Hamburg',
        ]);

        $this->command?->info('E2E-Daten angelegt. Passwort für alle Konten: ' . self::PASSWORD);
    }
}
