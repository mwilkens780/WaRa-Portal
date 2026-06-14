<?php

namespace App\Services\Import;

use App\Models\Competition;
use App\Models\ExtCompetitionResult;
use App\Models\ImportLog;
use App\Models\RelayMember;
use App\Models\RelayResult;
use App\Services\Dsv7Parser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Imports a DSV7 *-Pr.DSV7 file and persists ALL clubs + athletes into
 * ext_competition_results and relay_results.
 *
 * Own-club results are already handled by DsvImportService → competition_results.
 * This service is complementary and fills the cross-club data for rankings.
 */
class FullCompetitionImporter
{
    public function __construct(
        private Dsv7Parser            $parser,
        private AthleteMatchingService $matcher,
    ) {}

    public function importFile(string $filePath, Competition $competition, string $source = 'manual'): array
    {
        $stats = ['inserted' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $parsed = $this->parser->parseResults($filePath);
            $meet   = $parsed['meets'][0] ?? null;

            if (!$meet) {
                throw new \RuntimeException('Keine Wettkampfdaten in Datei gefunden.');
            }

            DB::transaction(function () use ($meet, $competition, &$stats) {
                foreach ($meet['clubs'] ?? [] as $club) {
                    foreach ($club['athletes'] ?? [] as $athleteData) {
                        $athlete = $this->matcher->findOrCreate([
                            'lastname'    => $athleteData['lastname'] ?? '',
                            'firstname'   => $athleteData['firstname'] ?? '',
                            'birth_year'  => $athleteData['birthdate']
                                ? (int) substr($athleteData['birthdate'], 0, 4)
                                : 0,
                            'gender'      => $this->normalizeGender($athleteData['gender'] ?? 'X'),
                            'dsv_id'      => $athleteData['dsvid'] ?? null,
                            'club'        => $club['name'] ?? null,
                            'nationality' => $athleteData['nationality'] ?? 'GER',
                        ]);

                        foreach ($athleteData['results'] ?? [] as $result) {
                            if (empty($result['discipline']) || empty($result['distance'])) {
                                continue;
                            }

                            try {
                                ExtCompetitionResult::updateOrCreate(
                                    [
                                        'competition_id' => $competition->id,
                                        'athlete_id'     => $athlete->id,
                                        'discipline'     => $result['discipline'],
                                        'distance'       => (int) $result['distance'],
                                        'age_group'      => $result['age_group'] ?? null,
                                    ],
                                    [
                                        'time_ms'    => $result['time_ms'] ?? null,
                                        'status'     => $result['status'] ?? 'OK',
                                        'placement'  => $result['placement'] ?? null,
                                        'gender'     => $this->normalizeGender($athleteData['gender'] ?? 'X'),
                                        'is_final'   => $result['is_final'] ?? false,
                                        'dsv_points' => $result['dsv_points'] ?? null,
                                    ]
                                );
                                $stats['inserted']++;
                            } catch (\Exception $e) {
                                $stats['errors']++;
                                Log::warning('ExtCompetitionResult upsert failed', ['error' => $e->getMessage()]);
                            }
                        }
                    }
                }
            });

            ImportLog::create([
                'source'         => $source,
                'filename'       => basename($filePath),
                'status'         => 'success',
                'competition_id' => $competition->id,
                'message'        => "Vollimport: {$stats['inserted']} Ergebnisse, {$stats['errors']} Fehler",
            ]);
        } catch (\Throwable $e) {
            $stats['errors']++;
            ImportLog::create([
                'source'   => $source,
                'filename' => basename($filePath),
                'status'   => 'error',
                'message'  => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    private function normalizeGender(string $g): string
    {
        return match(strtoupper($g)) {
            'W', 'F' => 'F',
            'M'      => 'M',
            default  => 'X',
        };
    }
}
