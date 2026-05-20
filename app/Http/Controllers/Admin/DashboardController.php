<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TrainingSession;
use App\Models\Competition;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'swimmers' => User::where('role', 'schwimmer')->count(),
            'trainers' => User::where('role', 'trainer')->count(),
            'parents' => User::where('role', 'elternteil')->count(),
            'inactive_users' => User::where('active', false)->count(),
            'sessions_this_month' => TrainingSession::whereMonth('date', now()->month)
                ->whereYear('date', now()->year)->count(),
            'competitions_total' => Competition::count(),
            'upcoming_competitions' => Competition::where('date', '>=', now())->count(),
        ];

        $recent_sessions = TrainingSession::with('trainer')
            ->orderByDesc('date')
            ->limit(5)
            ->get();

        $upcoming_competitions = Competition::where('date', '>=', now())
            ->orderBy('date')
            ->limit(3)
            ->get();

        return view('admin.dashboard', compact('stats', 'recent_sessions', 'upcoming_competitions'));
    }
}
