<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppTrace;
use App\Models\Setting;
use App\Models\TransactionLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        // ── Transaction log ────────────────────────────────────────────────
        $txQuery = TransactionLog::latest('created_at');

        if ($request->filled('tx_action')) {
            $txQuery->where('action', $request->tx_action);
        }
        if ($request->filled('tx_model')) {
            $txQuery->where('model_type', $request->tx_model);
        }
        if ($request->filled('tx_user')) {
            $txQuery->where('user_name', 'like', '%' . $request->tx_user . '%');
        }
        if ($request->filled('tx_from')) {
            $txQuery->whereDate('created_at', '>=', $request->tx_from);
        }
        if ($request->filled('tx_to')) {
            $txQuery->whereDate('created_at', '<=', $request->tx_to);
        }

        $transactions = $txQuery->paginate(50, ['*'], 'tx_page')->withQueryString();

        // Available model types for the filter dropdown
        $modelTypes = TransactionLog::distinct()->orderBy('model_type')->pluck('model_type');

        // ── Trace log ──────────────────────────────────────────────────────
        $trQuery = AppTrace::latest('created_at');

        if ($request->filled('tr_level')) {
            $trQuery->where('level', $request->tr_level);
        }
        if ($request->filled('tr_from')) {
            $trQuery->whereDate('created_at', '>=', $request->tr_from);
        }
        if ($request->filled('tr_to')) {
            $trQuery->whereDate('created_at', '<=', $request->tr_to);
        }

        $traces = $trQuery->paginate(50, ['*'], 'tr_page')->withQueryString();

        // ── Settings ───────────────────────────────────────────────────────
        $traceLevel            = (int) Setting::getCached('trace_level', '1');
        $transactionLogEnabled = (bool) Setting::getCached('transaction_log_enabled', '1');

        return view('admin.logs.index', compact(
            'transactions', 'modelTypes',
            'traces',
            'traceLevel', 'transactionLogEnabled'
        ));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'trace_level'             => ['required', 'integer', 'in:0,1,2'],
            'transaction_log_enabled' => ['required', 'boolean'],
        ]);

        Setting::set('trace_level',             (string) $data['trace_level']);
        Setting::set('transaction_log_enabled', $data['transaction_log_enabled'] ? '1' : '0');
        Setting::clearCache();

        return back()->with('success', 'Log-Einstellungen gespeichert.');
    }

    public function clearTransactions(Request $request)
    {
        $query = TransactionLog::query();
        if ($request->filled('before')) {
            $query->whereDate('created_at', '<', $request->before);
        }
        $count = $query->count();
        $query->delete();

        return back()->with('success', "{$count} Transaktionslog-Einträge gelöscht.");
    }

    public function clearTraces(Request $request)
    {
        $query = AppTrace::query();
        if ($request->filled('before')) {
            $query->whereDate('created_at', '<', $request->before);
        }
        $count = $query->count();
        $query->delete();

        return back()->with('success', "{$count} Trace-Einträge gelöscht.");
    }
}
