<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportLog;
use Illuminate\Http\Request;

class ImportLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ImportLog::with('competition')->orderByDesc('imported_at');

        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($von = $request->get('von')) {
            $query->whereDate('imported_at', '>=', $von);
        }
        if ($bis = $request->get('bis')) {
            $query->whereDate('imported_at', '<=', $bis);
        }

        $logs    = $query->paginate(50)->withQueryString();
        $filters = $request->only(['source', 'status', 'von', 'bis']);

        return view('admin.import-log.index', compact('logs', 'filters'));
    }
}
