<?php

namespace App\Services\Competition;

use App\Models\Competition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class AusschreibungGenerator
{
    /**
     * Generate a PDF Ausschreibung from the competition data.
     *
     * Returns the path to the generated file (in storage/app/ausschreibungen/).
     * Requires barryvdh/laravel-dompdf (composer require barryvdh/laravel-dompdf).
     */
    public function generate(Competition $competition): string
    {
        $competition->load(['events' => fn($q) => $q->orderBy('session_number')->orderBy('event_number')]);

        $sessions     = $competition->events->groupBy('session_number');
        $pflichtzeiten = $competition->events->where('qualifying_time_ms', '>', 0);
        $meldegelder  = $competition->events->where('meldegeld', '>', 0);

        $pdf = Pdf::loadView('competitions.ausschreibung-pdf', [
            'competition'  => $competition,
            'events'       => $competition->events,
            'sessions'     => $sessions,
            'pflichtzeiten'=> $pflichtzeiten,
            'meldegelder'  => $meldegelder,
        ])->setPaper('a4', 'portrait');

        $dir  = storage_path('app/ausschreibungen');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = $dir . '/' . $competition->id . '-ausschreibung.pdf';
        $pdf->save($filename);

        return $filename;
    }
}
