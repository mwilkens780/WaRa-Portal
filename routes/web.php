<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CompetitionController as AdminCompetitionController;
use App\Http\Controllers\Admin\CompetitionResultImportController;
use App\Http\Controllers\Trainer\DashboardController as TrainerDashboard;
use App\Http\Controllers\Trainer\TrainingSessionController;
use App\Http\Controllers\Trainer\DsvImportController;
use App\Http\Controllers\Swimmer\DashboardController as SwimmerDashboard;
use App\Http\Controllers\ParentArea\DashboardController as ParentDashboard;

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

// Admin-Bereich
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

    // Wettkämpfe (Admin verwaltet)
    Route::get('/wettkaempfe', [AdminCompetitionController::class, 'index'])->name('competitions.index');
    Route::get('/wettkaempfe/neu', [AdminCompetitionController::class, 'create'])->name('competitions.create');
    Route::post('/wettkaempfe/lenex', [AdminCompetitionController::class, 'parseLenex'])->name('competitions.lenex');
    Route::post('/wettkaempfe', [AdminCompetitionController::class, 'store'])->name('competitions.store');
    Route::get('/wettkaempfe/{competition}', [AdminCompetitionController::class, 'show'])->name('competitions.show');
    Route::get('/wettkaempfe/{competition}/bearbeiten', [AdminCompetitionController::class, 'edit'])->name('competitions.edit');
    Route::put('/wettkaempfe/{competition}', [AdminCompetitionController::class, 'update'])->name('competitions.update');
    Route::delete('/wettkaempfe/{competition}', [AdminCompetitionController::class, 'destroy'])->name('competitions.destroy');
    Route::post('/wettkaempfe/{competition}/ergebnis', [AdminCompetitionController::class, 'storeResult'])->name('competitions.result.store');
    Route::delete('/ergebnis/{result}', [AdminCompetitionController::class, 'destroyResult'])->name('competitions.result.destroy');

    // DSV-Ergebnisimport für bestehenden Wettkampf
    Route::post('/wettkaempfe/{competition}/ergebnisse-import', [CompetitionResultImportController::class, 'upload'])->name('competitions.results-import.upload');
    Route::get('/wettkaempfe/{competition}/ergebnisse-import/vorschau', [CompetitionResultImportController::class, 'preview'])->name('competitions.results-import.preview');
    Route::post('/wettkaempfe/{competition}/ergebnisse-import/speichern', [CompetitionResultImportController::class, 'execute'])->name('competitions.results-import.execute');
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

    // Trainingspläne hochladen
    Route::post('/training/{session}/teamplan', [TrainingSessionController::class, 'uploadTeamPlan'])->name('sessions.plan.team');
    Route::post('/training/{session}/einzelplan', [TrainingSessionController::class, 'uploadIndividualPlan'])->name('sessions.plan.individual');

    // DSV6/7 Ergebnisimport
    Route::get('/dsv-import', [DsvImportController::class, 'index'])->name('dsv-import.index');
    Route::post('/dsv-import/upload', [DsvImportController::class, 'upload'])->name('dsv-import.upload');
    Route::get('/dsv-import/preview', [DsvImportController::class, 'preview'])->name('dsv-import.preview');
    Route::post('/dsv-import/execute', [DsvImportController::class, 'execute'])->name('dsv-import.execute');
});

// Trainingsplan-Download & Tagebuch (alle eingeloggten Rollen)
Route::middleware('auth')->group(function () {
    Route::get('/training/{session}/plan/{type}', [TrainingSessionController::class, 'downloadPlan'])
        ->where('type', 'team|individual')
        ->name('sessions.plan.download');
    Route::post('/training/{session}/tagebuch', [TrainingSessionController::class, 'saveDiary'])
        ->name('sessions.diary');
});

// Schwimmer-Bereich
Route::middleware(['auth', 'role:schwimmer'])->prefix('schwimmer')->name('swimmer.')->group(function () {
    Route::get('/dashboard', [SwimmerDashboard::class, 'index'])->name('dashboard');
    Route::get('/meine-zeiten', [SwimmerDashboard::class, 'myTimes'])->name('times');
    Route::get('/wettkaempfe', [SwimmerDashboard::class, 'myCompetitions'])->name('competitions');
    Route::get('/training/{session}', [SwimmerDashboard::class, 'sessionDetail'])->name('session.show');
});

// Elternteil-Bereich
Route::middleware(['auth', 'role:elternteil'])->prefix('eltern')->name('parent.')->group(function () {
    Route::get('/dashboard', [ParentDashboard::class, 'index'])->name('dashboard');
    Route::get('/kind/{childId}/zeiten', [ParentDashboard::class, 'childTimes'])->name('child.times');
    Route::get('/kind/{childId}/wettkaempfe', [ParentDashboard::class, 'childCompetitions'])->name('child.competitions');
});
