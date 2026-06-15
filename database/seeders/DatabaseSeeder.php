<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\TrainingSession;
use App\Models\Competition;
use App\Models\CompetitionResult;
use App\Models\TrainingAttendance;
use App\Models\SwimmingTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::create([
            'name' => 'Admin Wasserratten',
            'email' => 'admin@wasserratten.de',
            'password' => Hash::make('Admin1234'),
            'role' => 'admin',
            'active' => true,
        ]);

        // Trainer
        $trainer = User::create([
            'name' => 'Max Mustermann',
            'email' => 'trainer@wasserratten.de',
            'password' => Hash::make('Trainer1234'),
            'role' => 'trainer',
            'active' => true,
            'phone' => '040-12345678',
        ]);

        // Schwimmer
        $swimmers = [];
        $swimmerData = [
            ['name' => 'Anna Schmidt', 'email' => 'anna@example.de', 'birth_date' => '2012-03-15'],
            ['name' => 'Felix Müller', 'email' => 'felix@example.de', 'birth_date' => '2010-07-22'],
            ['name' => 'Lisa Wagner', 'email' => 'lisa@example.de', 'birth_date' => '2011-11-08'],
            ['name' => 'Jonas Becker', 'email' => 'jonas@example.de', 'birth_date' => '2013-01-30'],
        ];

        foreach ($swimmerData as $data) {
            $swimmers[] = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('Schwimmer1234'),
                'role' => 'schwimmer',
                'birth_date' => $data['birth_date'],
                'active' => true,
            ]);
        }

        // Elternteil
        $parent = User::create([
            'name' => 'Maria Schmidt',
            'email' => 'eltern@example.de',
            'password' => Hash::make('Eltern1234'),
            'role' => 'elternteil',
            'active' => true,
        ]);
        // Anna ist Kind von Maria
        $parent->children()->attach($swimmers[0]->id);

        // Trainingseinheiten
        $sessionTitles = [
            ['title' => 'Techniktraining Freistil', 'type' => 'technik', 'days' => -14],
            ['title' => 'Ausdauereinheit', 'type' => 'ausdauer', 'days' => -10],
            ['title' => 'Wettkampfvorbereitung', 'type' => 'wettkampf', 'days' => -7],
            ['title' => 'Konditionstraining', 'type' => 'kondition', 'days' => -3],
            ['title' => 'Technik Brust & Rücken', 'type' => 'technik', 'days' => -1],
        ];

        foreach ($sessionTitles as $s) {
            $session = TrainingSession::create([
                'trainer_id' => $trainer->id,
                'title' => $s['title'],
                'date' => now()->addDays($s['days'])->format('Y-m-d'),
                'start_time' => '07:00',
                'end_time' => '08:30',
                'location' => 'Stadtbad Norderstedt',
                'type' => $s['type'],
                'notes' => 'Reguläres Training des SG Wasserratten.',
            ]);

            // Anwesenheit
            foreach ($swimmers as $i => $swimmer) {
                TrainingAttendance::create([
                    'training_session_id' => $session->id,
                    'user_id' => $swimmer->id,
                    'attended' => $i < 3, // erste 3 Schwimmer anwesend
                ]);
            }

            // Zeiten für Anna
            if (in_array($s['type'], ['technik', 'kondition'])) {
                SwimmingTime::create([
                    'user_id' => $swimmers[0]->id,
                    'training_session_id' => $session->id,
                    'discipline' => 'F',
                    'distance' => 100,
                    'time_ms' => 75000 + rand(-2000, 2000),
                    'is_personal_best' => false,
                ]);
            }
        }

        // Persönliche Bestzeit für Anna
        SwimmingTime::create([
            'user_id' => $swimmers[0]->id,
            'training_session_id' => null,
            'discipline' => 'F',
            'distance' => 100,
            'time_ms' => 72340,
            'is_personal_best' => true,
            'notes' => 'Persönliche Bestzeit',
        ]);

        // Wettkampf
        $comp = Competition::create([
            'name' => 'Hamburger Nachwuchsmeisterschaften 2025',
            'location' => 'Hamburg, Alster-Schwimmhalle',
            'date' => now()->subDays(30)->format('Y-m-d'),
            'type' => 'regional',
            'organizer' => 'Hamburger Schwimm-Verband',
            'description' => 'Jährliche Nachwuchsmeisterschaften für Jugend und Schüler.',
        ]);

        CompetitionResult::create([
            'competition_id' => $comp->id,
            'user_id' => $swimmers[0]->id,
            'discipline' => 'F',
            'distance' => 100,
            'time_ms' => 72340,
            'placement' => 3,
            'is_personal_best' => true,
            'age_group' => 'AK14',
        ]);

        CompetitionResult::create([
            'competition_id' => $comp->id,
            'user_id' => $swimmers[1]->id,
            'discipline' => 'B',
            'distance' => 100,
            'time_ms' => 83500,
            'placement' => 5,
            'is_personal_best' => false,
            'age_group' => 'AK16',
        ]);

        $this->call(MenuPermissionSeeder::class);

        $this->command->info('');
        $this->command->info('✓ Testdaten wurden angelegt. Login-Daten:');
        $this->command->info('  Admin:     admin@wasserratten.de / Admin1234');
        $this->command->info('  Trainer:   trainer@wasserratten.de / Trainer1234');
        $this->command->info('  Schwimmer: anna@example.de / Schwimmer1234');
        $this->command->info('  Elternteil: eltern@example.de / Eltern1234');
    }
}
