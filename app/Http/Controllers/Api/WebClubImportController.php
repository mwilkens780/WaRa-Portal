<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportLog;
use App\Services\Crawler\WebClubCrawler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebClubImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Shared Hosting: PHP-Timeout für große Imports hochsetzen.
        set_time_limit(300);

        $payload = $request->json()->all();

        if (empty($payload) || !is_array($payload)) {
            return response()->json(['error' => 'Leerer oder ungültiger Payload.'], 422);
        }

        try {
            $stats = (new WebClubCrawler())->processPayload($payload);

            ImportLog::create([
                'source'  => 'webclub_crawler',
                'status'  => 'success',
                'message' => sprintf(
                    'GitHub Actions Import: %d Wettkämpfe, %d Ergebnisse, %d Personen, %d Fehler.',
                    $stats['imported'],
                    $stats['results_synced'],
                    $stats['persons_synced'],
                    $stats['errors']
                ),
            ]);

            return response()->json(['ok' => true, 'stats' => $stats]);
        } catch (\Throwable $e) {
            ImportLog::create([
                'source'  => 'webclub_crawler',
                'status'  => 'error',
                'message' => 'GitHub Actions Import fehlgeschlagen: ' . $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
