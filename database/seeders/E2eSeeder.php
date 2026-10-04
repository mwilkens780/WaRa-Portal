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
        $parent  = $user('elternteil', 'Elke', 'Eltern', ['email' => 'eltern@e2e.test', 'mobile' => '0170 1234567', 'carpool_share_phone' => true]);
        $parent->children()->attach($swimmer->id);

        foreach ([$admin, $trainer, $swimmer, $second, $parent] as $u) {
            $u->forceFill(['portal_activated_at' => now()])->saveQuietly();
        }

        $group = TrainingGroup::create(['name' => 'E2E-Gruppe', 'color' => 'blue', 'group_type' => 'leistungssport', 'active' => true]);
        $group->trainers()->attach($trainer->id);
        $group->swimmers()->attach([$swimmer->id, $second->id]);

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

        Record::create([
            'type' => 'vereinsrekord', 'discipline' => 'F', 'distance' => 50, 'gender' => 'M', 'course' => 'Langbahn',
            'swimmer_name' => 'Ben Bahn', 'time_ms' => 24530, 'set_year' => now()->year - 1, 'location' => 'Hamburg',
        ]);

        $this->command?->info('E2E-Daten angelegt. Passwort für alle Konten: ' . self::PASSWORD);
    }
}
