<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainingSession;
use App\Models\TrainingAttendance;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $trainer = auth()->user();

        $stats = [
            'sessions_total'      => TrainingSession::where('trainer_id', $trainer->id)->count(),
            'sessions_this_month' => TrainingSession::where('trainer_id', $trainer->id)
                ->whereMonth('date', now()->month)->whereYear('date', now()->year)->count(),
            'active_swimmers'     => User::where('role', 'schwimmer')->where('active', true)->count(),
        ];

        $recent_sessions = TrainingSession::where('trainer_id', $trainer->id)
            ->withCount(['attendances as present_count' => fn($q) => $q->where('attended', true)])
            ->orderByDesc('date')->limit(5)->get();

        $upcoming = TrainingSession::where('trainer_id', $trainer->id)
            ->where('date', '>=', now())->orderBy('date')->limit(3)->get();

        // ── Chart-Daten: letzte 10 Einheiten Beteiligung ────────────────────
        $chartSessions = TrainingSession::where('trainer_id', $trainer->id)
            ->withCount([
                'attendances as present_count' => fn($q) => $q->where('attended', true),
                'attendances as total_count',
            ])
            ->orderByDesc('date')->limit(10)->get()->reverse()->values();

        $totalSwimmers = User::where('role', 'schwimmer')->where('active', true)->count();

        $chartLabels = $chartSessions->map(fn($s) => $s->date->format('d.m.'))->toArray();
        $chartData   = $chartSessions->map(
            fn($s) => $totalSwimmers > 0 ? round($s->present_count / $totalSwimmers * 100) : 0
        )->toArray();

        // ── Chart-Daten: Beteiligung pro Schwimmer (letzte 90 Tage) ─────────
        $swimmers = User::where('role', 'schwimmer')->where('active', true)->orderBy('name')->get();
        $since    = now()->subDays(90);

        $sessionCount90 = TrainingSession::where('date', '>=', $since)->count();

        $swimmerStats = $swimmers->map(function ($sw) use ($since, $sessionCount90) {
            $attended = TrainingAttendance::where('user_id', $sw->id)
                ->where('attended', true)
                ->whereHas('session', fn($q) => $q->where('date', '>=', $since))
                ->count();
            return [
                'name' => $sw->name,
                'pct'  => $sessionCount90 > 0 ? round($attended / $sessionCount90 * 100) : 0,
                'attended' => $attended,
                'total'    => $sessionCount90,
            ];
        });

        return view('trainer.dashboard', compact(
            'stats', 'recent_sessions', 'upcoming',
            'chartLabels', 'chartData', 'swimmerStats'
        ));
    }
}
