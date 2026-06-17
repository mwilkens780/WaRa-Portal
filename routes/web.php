<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CompetitionController as AdminCompetitionController;
use App\Http\Controllers\Admin\CompetitionResultImportController;
use App\Http\Controllers\Admin\WebClubCsvImportController;
use App\Http\Controllers\Admin\RecordController;
use App\Http\Controllers\Admin\CalendarEventController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\TrainingGroupController;
use App\Http\Controllers\Admin\WebClubImportController;
use App\Http\Controllers\Admin\CompetitionWebclubImportController;
use App\Http\Controllers\Admin\CompetitionEntryController;
use App\Http\Controllers\Admin\CompetitionSignupController;
use App\Http\Controllers\Admin\ImportLogController;
use App\Http\Controllers\Swimmer\SignupController as SwimmerSignupController;
use App\Http\Controllers\Trainer\DashboardController as TrainerDashboard;
use App\Http\Controllers\Trainer\TrainingSessionController;
use App\Http\Controllers\Trainer\DsvImportController;
use App\Http\Controllers\Trainer\TrainingPlanController;
use App\Http\Controllers\Swimmer\DashboardController as SwimmerDashboard;
use App\Http\Controllers\Swimmer\GoalController as SwimmerGoalController;
use App\Http\Controllers\Trainer\GoalController as TrainerGoalController;
use App\Http\Controllers\Trainer\HallBookingController;
use App\Http\Controllers\ParentArea\DashboardController as ParentDashboard;
use App\Http\Controllers\ParentArea\TrainingController as ParentTrainingController;
use App\Http\Controllers\ParentArea\SignupController as ParentSignupController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Trainer\UserLiteController;
use App\Http\Controllers\Swimmer\SessionPlanningController;
use App\Http\Controllers\Trainer\SessionSwimmerController;

// Startseite -> Login
Route::get('/', fn() => redirect()->route('login'));

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Passwort ändern (alle Rollen)
    Route::get('/passwort-aendern', [PasswordController::class, 'showChangeForm'])->name('password.change');
    Route::put('/passwort-aendern', [PasswordController::class, 'update'])->name('password.update');
});

// Admin-only Bereich
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // Benutzerverwaltung
    Route::get('/benutzer', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/benutzer/neu', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/benutzer', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/benutzer/{user}/bearbeiten', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/benutzer/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/benutzer/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::patch('/benutzer/{user}/aktivierung', [AdminUserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::post('/benutzer/{user}/passwort-reset', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
    Route::delete('/benutzer-alle', [AdminUserController::class, 'destroyAll'])->name('users.destroy-all');
    Route::post('/benutzer/dsv-bereinigen', [AdminUserController::class, 'cleanupDsvIds'])->name('users.cleanup-dsv');

    // WebClub Wettkampf-Terminimport
    Route::get('/wettkaempfe/webclub-import', [CompetitionWebclubImportController::class, 'showForm'])->name('competitions.webclub-import.form');
    Route::post('/wettkaempfe/webclub-import/vorschau', [CompetitionWebclubImportController::class, 'preview'])->name('competitions.webclub-import.preview');
    Route::post('/wettkaempfe/webclub-import/speichern', [CompetitionWebclubImportController::class, 'import'])->name('competitions.webclub-import.import');

    // Wettkämpfe – nur Admin darf anlegen, bearbeiten, löschen, Ergebnisse manuell eintragen
    Route::get('/wettkaempfe/neu', [AdminCompetitionController::class, 'create'])->name('competitions.create');
    Route::post('/wettkaempfe/lenex', [AdminCompetitionController::class, 'parseLenex'])->name('competitions.lenex');
    Route::post('/wettkaempfe', [AdminCompetitionController::class, 'store'])->name('competitions.store');
    Route::get('/wettkaempfe/{competition}/bearbeiten', [AdminCompetitionController::class, 'edit'])->name('competitions.edit');
    Route::put('/wettkaempfe/{competition}', [AdminCompetitionController::class, 'update'])->name('competitions.update');
    Route::delete('/wettkaempfe/{competition}', [AdminCompetitionController::class, 'destroy'])->name('competitions.destroy');
    Route::post('/wettkaempfe/{competition}/gruppen', [AdminCompetitionController::class, 'syncGroups'])->name('competitions.sync-groups');
    Route::post('/wettkaempfe/{competition}/ergebnis', [AdminCompetitionController::class, 'storeResult'])->name('competitions.result.store');
    Route::delete('/ergebnis/{result}', [AdminCompetitionController::class, 'destroyResult'])->name('competitions.result.destroy');

    // Rekorde – Verwaltung (Admin only)
    Route::post('/rekorde', [RecordController::class, 'store'])->name('records.store');
    Route::delete('/rekorde/{record}', [RecordController::class, 'destroy'])->name('records.destroy');
    Route::post('/rekorde/import/upload', [RecordController::class, 'importUpload'])->name('records.import.upload');
    Route::get('/rekorde/import/vorschau', [RecordController::class, 'importPreview'])->name('records.import.preview');
    Route::post('/rekorde/import/speichern', [RecordController::class, 'importExecute'])->name('records.import.execute');
    Route::post('/rekorde/recheck', [RecordController::class, 'recheckAll'])->name('records.recheck');

    // Protokoll (Transaction Log + Traces + Settings)
    Route::get('/protokoll', [LogController::class, 'index'])->name('logs.index');
    Route::post('/protokoll/einstellungen', [LogController::class, 'updateSettings'])->name('logs.settings');
    Route::delete('/protokoll/transaktionen', [LogController::class, 'clearTransactions'])->name('logs.transactions.clear');
    Route::delete('/protokoll/traces', [LogController::class, 'clearTraces'])->name('logs.traces.clear');

    // WebClub Mitglieder-Import
    Route::get('/mitglieder-import', [WebClubImportController::class, 'index'])->name('webclub-import.index');
    Route::post('/mitglieder-import/upload', [WebClubImportController::class, 'upload'])->name('webclub-import.upload');
    Route::get('/mitglieder-import/vorschau', [WebClubImportController::class, 'preview'])->name('webclub-import.preview');
    Route::post('/mitglieder-import/speichern', [WebClubImportController::class, 'execute'])->name('webclub-import.execute');

    // Trainingsgruppen – Anlegen + Löschen: Admin only
    Route::get('/trainingsgruppen/neu', [TrainingGroupController::class, 'create'])->name('training-groups.create');
    Route::post('/trainingsgruppen', [TrainingGroupController::class, 'store'])->name('training-groups.store');
    Route::delete('/trainingsgruppen/{trainingGroup}', [TrainingGroupController::class, 'destroy'])->name('training-groups.destroy');
});

// Trainingsgruppen – Index, Show, Edit: Trainer + Admin
Route::middleware(['auth', 'role:trainer,admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/trainingsgruppen', [TrainingGroupController::class, 'index'])->name('training-groups.index');
    Route::get('/trainingsgruppen/{trainingGroup}', [TrainingGroupController::class, 'show'])->name('training-groups.show');
    Route::get('/trainingsgruppen/{trainingGroup}/bearbeiten', [TrainingGroupController::class, 'edit'])->name('training-groups.edit');
    Route::put('/trainingsgruppen/{trainingGroup}', [TrainingGroupController::class, 'update'])->name('training-groups.update');
    // Schwimmer entfernen
    Route::delete('/trainingsgruppen/{trainingGroup}/schwimmer/{user}', [TrainingGroupController::class, 'removeSwimmer'])->name('training-groups.remove-swimmer');
    // CSV-Import
    Route::post('/trainingsgruppen/{trainingGroup}/csv-upload', [TrainingGroupController::class, 'importCsvUpload'])->name('training-groups.csv-upload');
    Route::get('/trainingsgruppen/{trainingGroup}/csv-vorschau', [TrainingGroupController::class, 'importCsvPreview'])->name('training-groups.csv-preview');
    Route::post('/trainingsgruppen/{trainingGroup}/csv-speichern', [TrainingGroupController::class, 'importCsvExecute'])->name('training-groups.csv-execute');
});

// Wettkämpfe – Ansicht & Import auch für Trainer, Vorstand, Kampfrichter zugänglich
Route::middleware(['auth', 'role:trainer,vorstand,kampfrichter,admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/wettkaempfe', [AdminCompetitionController::class, 'index'])->name('competitions.index');
    Route::get('/wettkaempfe/{competition}', [AdminCompetitionController::class, 'show'])->name('competitions.show');
    Route::post('/wettkaempfe/{competition}/auswertung', [AdminCompetitionController::class, 'generateAnalysis'])->name('competitions.analysis');
    Route::post('/wettkaempfe/{competition}/auswertung/speichern', [AdminCompetitionController::class, 'saveAnalysis'])->name('competitions.analysis.save');
    Route::get('/wettkaempfe/{competition}/auswertung/pdf', [AdminCompetitionController::class, 'exportAnalysisPdf'])->name('competitions.analysis.pdf');
    Route::post('/wettkaempfe/{competition}/ergebnisse-import', [CompetitionResultImportController::class, 'upload'])->name('competitions.results-import.upload');
    Route::get('/wettkaempfe/{competition}/ergebnisse-import/vorschau', [CompetitionResultImportController::class, 'preview'])->name('competitions.results-import.preview');
    Route::post('/wettkaempfe/{competition}/ergebnisse-import/speichern', [CompetitionResultImportController::class, 'execute'])->name('competitions.results-import.execute');
    Route::post('/wettkaempfe/{competition}/webclub-csv-import', [WebClubCsvImportController::class, 'upload'])->name('competitions.wc-import.upload');
    Route::get('/wettkaempfe/{competition}/webclub-csv-import/vorschau', [WebClubCsvImportController::class, 'preview'])->name('competitions.wc-import.preview');
    Route::post('/wettkaempfe/{competition}/webclub-csv-import/speichern', [WebClubCsvImportController::class, 'execute'])->name('competitions.wc-import.execute');

    // Organisation-Notizen speichern
    Route::post('/wettkaempfe/{competition}/organisation', [AdminCompetitionController::class, 'saveOrganisation'])->name('competitions.organisation.save');

    // Ausschreibungs-Import (PDF → Claude → strukturierte Daten)
    Route::post('/wettkaempfe/{competition}/ausschreibung/parsen',     [AdminCompetitionController::class, 'parseAnnouncement'])->name('competitions.announcement.parse');
    Route::post('/wettkaempfe/{competition}/ausschreibung/speichern',  [AdminCompetitionController::class, 'saveAnnouncement'])->name('competitions.announcement.save');

    // Meldungen (Entries + DSV7-Generatoren)
    Route::get('/wettkaempfe/{competition}/meldungen/entries', [CompetitionEntryController::class, 'index'])->name('competitions.entries.index');
    Route::post('/wettkaempfe/{competition}/meldungen/entries', [CompetitionEntryController::class, 'store'])->name('competitions.entries.store');
    Route::delete('/wettkaempfe/{competition}/meldungen/entries/{entry}', [CompetitionEntryController::class, 'destroy'])->name('competitions.entries.destroy');
    Route::post('/wettkaempfe/{competition}/meldungen/staffel', [CompetitionEntryController::class, 'storeRelay'])->name('competitions.entries.relay');
    Route::get('/wettkaempfe/{competition}/dsv7/meldedatei', [CompetitionEntryController::class, 'downloadMeldedatei'])->name('competitions.dsv7.meldedatei');
    Route::get('/wettkaempfe/{competition}/dsv7/definitionsdatei', [CompetitionEntryController::class, 'downloadDefinitionsdatei'])->name('competitions.dsv7.definitionsdatei');
    Route::get('/wettkaempfe/{competition}/dsv7/ausschreibung-pdf', [CompetitionEntryController::class, 'downloadAusschreibungPdf'])->name('competitions.dsv7.ausschreibung-pdf');
    Route::post('/wettkaempfe/{competition}/vollimport', [AdminCompetitionController::class, 'fullImport'])->name('competitions.full-import');

    // Import-Log
    Route::get('/import-log', [ImportLogController::class, 'index'])->name('import-log.index');

    // Anmeldeabfrage (Signup-Workflow)
    Route::post('/wettkaempfe/{competition}/anmeldung', [CompetitionSignupController::class, 'store'])->name('competitions.signup.store');
    Route::put('/wettkaempfe/{competition}/anmeldung/{signupRequest}', [CompetitionSignupController::class, 'update'])->name('competitions.signup.update');
    Route::post('/wettkaempfe/{competition}/anmeldung/{signupRequest}/aktivieren', [CompetitionSignupController::class, 'activate'])->name('competitions.signup.activate');
    Route::post('/wettkaempfe/{competition}/anmeldung/{signupRequest}/schliessen', [CompetitionSignupController::class, 'close'])->name('competitions.signup.close');
    Route::post('/wettkaempfe/{competition}/anmeldung/{signupRequest}/erinnern', [CompetitionSignupController::class, 'remind'])->name('competitions.signup.remind');
    Route::delete('/wettkaempfe/{competition}/anmeldung/{signupRequest}', [CompetitionSignupController::class, 'destroy'])->name('competitions.signup.destroy');
    Route::get('/wettkaempfe/{competition}/anmeldung/{signupRequest}/anhang', [CompetitionSignupController::class, 'downloadAttachment'])->name('competitions.signup.attachment');
});

// Rekorde – Ansicht für Trainer, Vorstand und Admin
Route::middleware(['auth', 'role:trainer,vorstand,admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/rekorde', [RecordController::class, 'index'])->name('records.index');
});

// Trainer-Bereich (Trainer + Admin)
Route::middleware(['auth', 'role:trainer,admin'])->prefix('trainer')->name('trainer.')->group(function () {
    Route::get('/dashboard', [TrainerDashboard::class, 'index'])->name('dashboard');

    // Trainingseinheiten
    Route::get('/training', [TrainingSessionController::class, 'index'])->name('sessions.index');
    Route::get('/training/neu', [TrainingSessionController::class, 'create'])->name('sessions.create');
    Route::post('/training', [TrainingSessionController::class, 'store'])->name('sessions.store');
    Route::get('/training/{session}', [TrainingSessionController::class, 'show'])->name('sessions.show');
    Route::get('/training/{session}/bearbeiten', [TrainingSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('/training/{session}', [TrainingSessionController::class, 'update'])->name('sessions.update');
    Route::delete('/training/{session}', [TrainingSessionController::class, 'destroy'])->name('sessions.destroy');

    // Anwesenheit & Zeiten
    Route::post('/training/{session}/anwesenheit', [TrainingSessionController::class, 'saveAttendance'])->name('sessions.attendance');
    Route::post('/training/{session}/zeit', [TrainingSessionController::class, 'saveTime'])->name('sessions.time');
    Route::delete('/zeiten/{time}', [TrainingSessionController::class, 'destroyTime'])->name('times.destroy');

    // Wiederholungsgruppe löschen
    Route::delete('/training/{session}/gruppe', [TrainingSessionController::class, 'destroyGroup'])->name('sessions.destroy-group');

    // Druckansicht
    Route::get('/training/{session}/drucken', [TrainingSessionController::class, 'printView'])->name('sessions.print');

    // Trainingspläne hochladen
    Route::post('/training/{session}/teamplan', [TrainingSessionController::class, 'uploadTeamPlan'])->name('sessions.plan.team');
    Route::post('/training/{session}/einzelplan', [TrainingSessionController::class, 'uploadIndividualPlan'])->name('sessions.plan.individual');

    // DSV6/7 Ergebnisimport
    Route::get('/dsv-import', [DsvImportController::class, 'index'])->name('dsv-import.index');
    Route::post('/dsv-import/upload', [DsvImportController::class, 'upload'])->name('dsv-import.upload');
    Route::get('/dsv-import/preview', [DsvImportController::class, 'preview'])->name('dsv-import.preview');
    Route::post('/dsv-import/execute', [DsvImportController::class, 'execute'])->name('dsv-import.execute');

    // Trainingsplan-Builder
    Route::get('/training/{session}/trainingsplan', [TrainingPlanController::class, 'edit'])->name('sessions.plan.builder');
    Route::post('/training/{session}/trainingsplan', [TrainingPlanController::class, 'save'])->name('sessions.plan.save');
    Route::delete('/training/{session}/trainingsplan/anhang', [TrainingPlanController::class, 'deleteAttachment'])->name('sessions.plan.attachment.delete');
    Route::post('/training/{session}/block-zeiten', [TrainingPlanController::class, 'saveBlockTime'])->name('sessions.block-times.save');

    // Ziele
    Route::get('/ziele', [TrainerGoalController::class, 'index'])->name('goals.index');
    Route::post('/ziele/{goal}/kommentar', [TrainerGoalController::class, 'storeComment'])->name('goals.comment');
    Route::post('/gruppen-ziele', [TrainerGoalController::class, 'storeGroupGoal'])->name('group-goals.store');
    Route::put('/gruppen-ziele/{groupGoal}', [TrainerGoalController::class, 'updateGroupGoal'])->name('group-goals.update');
    Route::delete('/gruppen-ziele/{groupGoal}', [TrainerGoalController::class, 'destroyGroupGoal'])->name('group-goals.destroy');

    // Hallenbelegung
    Route::get('/hall', [HallBookingController::class, 'index'])->name('hall.index');
    Route::post('/hall/bookings', [HallBookingController::class, 'store'])->name('hall.bookings.store');
    Route::put('/hall/bookings/{booking}', [HallBookingController::class, 'update'])->name('hall.bookings.update');
    Route::delete('/hall/bookings/{booking}', [HallBookingController::class, 'destroy'])->name('hall.bookings.destroy');
    Route::get('/hall/conflicts', [HallBookingController::class, 'conflicts'])->name('hall.conflicts');
    Route::get('/hall/sessions/search', [HallBookingController::class, 'searchSessions'])->name('hall.sessions.search');

    // Trainingseinheit → Bahnbelegung
    Route::post('/training/{session}/bahnen', [TrainingSessionController::class, 'bookLanes'])->name('sessions.book-lanes');
    Route::delete('/training/{session}/bahnen/{booking}', [TrainingSessionController::class, 'removeLane'])->name('sessions.remove-lane');

    // Individuelle Schwimmer-Zuweisung zu Einheit oder Serie
    Route::post('/training/{session}/schwimmer', [SessionSwimmerController::class, 'addToSession'])->name('sessions.swimmer.add');
    Route::delete('/training/{session}/schwimmer/{user}', [SessionSwimmerController::class, 'removeFromSession'])->name('sessions.swimmer.remove');
    Route::post('/training-serien/{recurrenceGroupId}/schwimmer', [SessionSwimmerController::class, 'addToSeries'])->name('sessions.series.swimmer.add');
    Route::delete('/training-serien/{recurrenceGroupId}/schwimmer/{user}', [SessionSwimmerController::class, 'removeFromSeries'])->name('sessions.series.swimmer.remove');
});

// Trainingsplan-Download & Tagebuch (alle eingeloggten Rollen)
Route::middleware('auth')->group(function () {
    Route::get('/training/{session}/plan/{type}', [TrainingSessionController::class, 'downloadPlan'])
        ->where('type', 'team|individual')
        ->name('sessions.plan.download');
    Route::get('/training/{session}/trainingsplan/anhang/download', [TrainingPlanController::class, 'downloadAttachment'])
        ->name('sessions.plan.attachment.download');
    Route::post('/training/{session}/tagebuch', [TrainingSessionController::class, 'saveDiary'])
        ->name('sessions.diary');
});

// Kalender (alle eingeloggten Rollen können lesen; Trainer+Admin dürfen Termine anlegen/bearbeiten)
Route::middleware('auth')->group(function () {
    Route::get('/kalender', [CalendarController::class, 'index'])->name('calendar.index');
});
Route::middleware(['auth', 'role:trainer,admin'])->group(function () {
    Route::get('/kalender/termin/neu', [CalendarEventController::class, 'create'])->name('calendar.events.create');
    Route::post('/kalender/termin', [CalendarEventController::class, 'store'])->name('calendar.events.store');
    Route::get('/kalender/termin/{calendarEvent}/bearbeiten', [CalendarEventController::class, 'edit'])->name('calendar.events.edit');
    Route::put('/kalender/termin/{calendarEvent}', [CalendarEventController::class, 'update'])->name('calendar.events.update');
    Route::delete('/kalender/termin/{calendarEvent}', [CalendarEventController::class, 'destroy'])->name('calendar.events.destroy');
});

// Berechtigungs-Matrix + Einstellungen (nur Admin)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/berechtigungen', [PermissionMatrixController::class, 'index'])->name('admin.permissions.index');
    Route::put('/admin/berechtigungen', [PermissionMatrixController::class, 'update'])->name('admin.permissions.update');

    Route::get('/admin/einstellungen', [SettingsController::class, 'index'])->name('admin.settings.index');
    Route::put('/admin/einstellungen', [SettingsController::class, 'update'])->name('admin.settings.update');
});

// Benutzerverwaltung Lite (Trainer + Vorstand)
Route::middleware(['auth', 'role:trainer,vorstand,admin'])->prefix('benutzer')->name('users-lite.')->group(function () {
    Route::get('/',                    [UserLiteController::class, 'index'])->name('index');
    Route::get('/neu',                 [UserLiteController::class, 'create'])->name('create');
    Route::post('/',                   [UserLiteController::class, 'store'])->name('store');
    Route::get('/{user}/bearbeiten',   [UserLiteController::class, 'edit'])->name('edit');
    Route::put('/{user}',              [UserLiteController::class, 'update'])->name('update');
    Route::post('/{user}/toggle',      [UserLiteController::class, 'toggleActive'])->name('toggle');
});

// Scheduler-Trigger für URL-Cron (all-inkl.com unterstützt kein Shell-Cron)
Route::get('/cron/run/{token}', function (string $token) {
    if (!hash_equals(config('cron.scheduler_token', ''), $token)) {
        abort(403);
    }
    \Illuminate\Support\Facades\Artisan::call('schedule:run');
    return response('OK ' . now()->toDateTimeString(), 200)
        ->header('Content-Type', 'text/plain');
})->name('cron.run');

// Schwimmer-Bereich
Route::middleware(['auth', 'role:schwimmer'])->prefix('schwimmer')->name('swimmer.')->group(function () {
    Route::get('/dashboard', [SwimmerDashboard::class, 'index'])->name('dashboard');
    Route::post('/anmeldung/{signupRequest}/antworten', [SwimmerSignupController::class, 'respond'])->name('signup.respond');
    Route::post('/anmeldung/{signupRequest}/bus', [SwimmerSignupController::class, 'toggleBus'])->name('signup.bus');
    Route::get('/meine-zeiten', [SwimmerDashboard::class, 'myTimes'])->name('times');
    Route::get('/wettkaempfe', [SwimmerDashboard::class, 'myCompetitions'])->name('competitions');
    Route::get('/meine-trainings', [SwimmerDashboard::class, 'myTrainings'])->name('sessions');
    Route::get('/training/{session}', [SwimmerDashboard::class, 'sessionDetail'])->name('session.show');
    Route::post('/training/{session}/absage', [SwimmerDashboard::class, 'cancelSession'])->name('session.cancel');

    // Trainingsplanung: Serien ausblenden/einblenden
    Route::post('/serien/{recurrenceGroupId}/ausblenden', [SessionPlanningController::class, 'excludeSeries'])->name('series.exclude');
    Route::delete('/serien/{recurrenceGroupId}/ausblenden', [SessionPlanningController::class, 'includeSeries'])->name('series.include');

    // Registrierung für offene Einheiten
    Route::post('/training/{session}/anmelden', [SessionPlanningController::class, 'register'])->name('session.register');
    Route::delete('/training/{session}/anmelden', [SessionPlanningController::class, 'unregister'])->name('session.unregister');
    Route::post('/training/{session}/einzel-beitreten', [SessionPlanningController::class, 'punctualJoin'])->name('session.punctual.join');

    // Ziele
    Route::get('/meine-ziele', [SwimmerGoalController::class, 'index'])->name('goals.index');
    Route::post('/meine-ziele', [SwimmerGoalController::class, 'store'])->name('goals.store');
    Route::delete('/meine-ziele/{goal}', [SwimmerGoalController::class, 'destroy'])->name('goals.destroy');
    Route::post('/meine-ziele/{goal}/bewerten', [SwimmerGoalController::class, 'evaluate'])->name('goals.evaluate');
    Route::patch('/meine-ziele/{goal}/fortschritt', [SwimmerGoalController::class, 'updateProgress'])->name('goals.progress');
});

// Elternteil-Bereich
Route::middleware(['auth', 'role:elternteil'])->prefix('eltern')->name('parent.')->group(function () {
    Route::get('/dashboard', [ParentDashboard::class, 'index'])->name('dashboard');
    Route::get('/kind/{childId}/zeiten', [ParentDashboard::class, 'childTimes'])->name('child.times');
    Route::get('/kind/{childId}/wettkaempfe', [ParentDashboard::class, 'childCompetitions'])->name('child.competitions');

    // Training: view upcoming sessions + register/cancel for child
    Route::get('/kind/{childId}/training', [ParentTrainingController::class, 'childTrainings'])->name('child.trainings');
    Route::post('/kind/{childId}/training/{session}/absage', [ParentTrainingController::class, 'cancelSession'])->name('child.session.cancel');
    Route::post('/kind/{childId}/training/{session}/anmelden', [ParentTrainingController::class, 'register'])->name('child.session.register');
    Route::delete('/kind/{childId}/training/{session}/anmelden', [ParentTrainingController::class, 'unregister'])->name('child.session.unregister');

    // Competition signups: view + respond on behalf of child (with carpool/overnight/dinner)
    Route::get('/kind/{childId}/anmeldungen', [ParentSignupController::class, 'childSignups'])->name('child.signups');
    Route::post('/kind/{childId}/anmeldungen/{signupRequest}/antworten', [ParentSignupController::class, 'respond'])->name('child.signup.respond');
});
