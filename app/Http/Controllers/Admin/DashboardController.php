<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitionResult;
use App\Models\Setting;
use App\Models\SupportTicket;
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

        $recent_sessions = TrainingSession::with('coTrainers:id,firstname,lastname')
            ->orderByDesc('date')
            ->limit(5)
            ->get();

        $upcoming_competitions = Competition::where('date', '>=', now())
            ->orderBy('date')
            ->limit(3)
            ->get();

        [$seasonStart, $seasonEnd] = $this->currentSeasonRange();

        $new_records = CompetitionResult::with(['user', 'competition'])
            ->where(fn($q) => $q->where('breaks_vereinsrekord', true)->orWhere('breaks_landesrekord', true))
            ->where(fn($q) => $q->whereNull('age_group')->orWhere('age_group', ''))
            ->whereHas('competition', fn($q) => $q->whereBetween('date', [$seasonStart, $seasonEnd]))
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $maintenanceMode  = Setting::getBool('maintenance_mode');
        $openTickets      = SupportTicket::whereNull('github_closed_at')->count();
        $githubIssuesUrl  = 'https://github.com/' . config('services.github.repo', 'mwilkens780/WaRa-Portal') . '/issues';

        return view('admin.dashboard', compact(
            'stats', 'recent_sessions', 'upcoming_competitions',
            'new_records', 'maintenanceMode', 'openTickets', 'githubIssuesUrl'
        ));
    }

    private function currentSeasonRange(): array
    {
        $month = now()->month;
        $year  = now()->year;
        if ($month >= 4 && $month <= 9) {
            return ["$year-04-01", "$year-09-30"];
        }
        $start = $month >= 10 ? "$year-10-01" : ($year - 1) . "-10-01";
        $end   = $month >= 10 ? ($year + 1) . "-03-31" : "$year-03-31";
        return [$start, $end];
    }
}
