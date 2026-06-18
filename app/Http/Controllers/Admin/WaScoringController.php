<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaScoringTable;
use App\Services\WaScoringService;
use Illuminate\Http\Request;

class WaScoringController extends Controller
{
    public function index(Request $request)
    {
        $year       = (int) ($request->year       ?? WaScoringTable::max('year') ?? date('Y'));
        $poolLength = (int) ($request->pool_length ?? 50);

        $entries = WaScoringTable::where('year', $year)
            ->where('pool_length', $poolLength)
            ->orderBy('gender')
            ->orderBy('discipline')
            ->orderBy('distance_m')
            ->get()
            ->keyBy(fn($e) => "{$e->gender}_{$e->discipline}_{$e->distance_m}");

        $years       = WaScoringTable::selectRaw('DISTINCT year')->orderByDesc('year')->pluck('year');
        $disciplines = WaScoringTable::disciplines();

        return view('admin.wa_scoring.index', compact('entries', 'year', 'poolLength', 'years', 'disciplines'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year'        => ['required', 'integer', 'min:2000', 'max:2099'],
            'pool_length' => ['required', 'in:25,50'],
            'gender'      => ['required', 'in:M,F'],
            'discipline'  => ['required', 'in:F,B,R,S,L'],
            'distance_m'  => ['required', 'integer', 'min:50', 'max:1500'],
            'base_time'   => ['required', 'string'],
        ]);

        $baseMs = WaScoringService::parseTimeInput($data['base_time']);
        if (!$baseMs) {
            return back()->withErrors(['base_time' => 'Ungültiges Zeitformat (erwartet: M:SS,cs oder SS,cs)']);
        }

        WaScoringTable::updateOrCreate(
            [
                'year'        => $data['year'],
                'pool_length' => $data['pool_length'],
                'gender'      => $data['gender'],
                'discipline'  => $data['discipline'],
                'distance_m'  => $data['distance_m'],
            ],
            ['base_time_ms' => $baseMs]
        );

        return back()->with('success', 'Basiszeit gespeichert.');
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'year'        => ['required', 'integer', 'min:2000', 'max:2099'],
            'pool_length' => ['required', 'in:25,50'],
            'times'       => ['required', 'array'],
            'times.*'     => ['nullable', 'string'],
        ]);

        $saved = 0;
        foreach ($data['times'] as $key => $timeStr) {
            if (empty(trim($timeStr ?? ''))) continue;

            // key format: "M_F_100" (gender_discipline_distance)
            [$gender, $discipline, $distance] = explode('_', $key, 3);
            if (!in_array($gender, ['M', 'F']) || !in_array($discipline, ['F', 'B', 'R', 'S', 'L'])) continue;

            $baseMs = WaScoringService::parseTimeInput($timeStr);
            if (!$baseMs) continue;

            WaScoringTable::updateOrCreate(
                [
                    'year'        => $data['year'],
                    'pool_length' => $data['pool_length'],
                    'gender'      => $gender,
                    'discipline'  => $discipline,
                    'distance_m'  => (int) $distance,
                ],
                ['base_time_ms' => $baseMs]
            );
            $saved++;
        }

        return back()->with('success', "{$saved} Basiszeiten gespeichert.");
    }

    public function destroy(WaScoringTable $waScoringTable)
    {
        $waScoringTable->delete();
        return back()->with('success', 'Basiszeit gelöscht.');
    }
}
