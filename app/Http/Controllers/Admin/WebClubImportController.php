<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WebClubImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WebClubImportController extends Controller
{
    public function __construct(private WebClubImportService $service) {}

    public function index()
    {
        return view('admin.webclub-import.index');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('csv_file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['csv', 'txt'])) {
            return back()->withErrors(['csv_file' => 'Nur .csv oder .txt Dateien sind erlaubt.']);
        }

        $path     = $file->store('webclub-imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $parsed = $this->service->parse($fullPath);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->withErrors(['csv_file' => 'Fehler beim Einlesen: ' . $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        if (empty($parsed['rows'])) {
            return back()->withErrors(['csv_file' => 'Keine Datensätze gefunden. Bitte prüfe das Dateiformat.']);
        }

        session(['webclub_import' => $parsed]);

        return redirect()->route('admin.webclub-import.preview');
    }

    public function preview()
    {
        if (!session()->has('webclub_import')) {
            return redirect()->route('admin.webclub-import.index')
                ->with('error', 'Sitzung abgelaufen – bitte Datei erneut hochladen.');
        }

        $parsed = session('webclub_import');

        return view('admin.webclub-import.preview', [
            'rows'  => $parsed['rows'],
            'stats' => $parsed['stats'],
        ]);
    }

    public function execute(Request $request)
    {
        $parsed = session('webclub_import');

        if (!$parsed) {
            return redirect()->route('admin.webclub-import.index')
                ->with('error', 'Sitzung abgelaufen – bitte Datei erneut hochladen.');
        }

        // roles[index] = selected role value, or '' / absent = skip
        $roleOverrides  = $request->input('roles', []);
        $rows           = $parsed['rows'];
        $inactiveErrors = [];

        foreach ($rows as $index => &$row) {
            $selectedRole = trim($roleOverrides[$index] ?? '');

            if ($selectedRole === '' || !in_array($selectedRole, User::ROLES, true)) {
                // No role selected (or "–" empty option) → skip
                $row['action'] = 'skip';
                continue;
            }

            // Role has been assigned — set it in data
            $row['data']['role'] = $selectedRole;
            $row['role']         = $selectedRole;

            // If the row was originally a skip (no WebClub role detected),
            // determine action from whether the user already exists.
            if ($row['action'] === 'skip') {
                // Person left the club → report error, do not import
                if (($row['data']['active'] ?? true) === false) {
                    $inactiveErrors[] = [
                        'name'    => $row['name'],
                        'message' => 'Person ist ausgetreten (Aktiv = Nein) und kann nicht importiert werden.',
                    ];
                    continue; // keep action='skip'
                }

                $row['roles']  = [$selectedRole];
                $row['action'] = $row['user_id'] ? 'update' : 'new';
            }
        }
        unset($row);

        $result = $this->service->execute($rows);

        // Prepend inactive-person errors to any DB-level errors from the service
        $result['errors'] = array_merge($inactiveErrors, $result['errors']);

        session()->forget('webclub_import');

        return redirect()->route('admin.webclub-import.index')
            ->with('import_success', $result);
    }
}
