<?php

namespace App\Services\Competition;

use Illuminate\Support\Facades\Http;

/**
 * Parses a competition announcement PDF (Ausschreibung) into structured data.
 *
 * Sends the PDF directly to the Claude API as a native document — no PHP PDF
 * library needed. Claude handles extraction, table parsing, and OCR internally.
 */
class AusschreibungParserService
{
    private const MAX_PDF_BYTES = 10 * 1024 * 1024; // 10 MB

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Parse a PDF file and return structured announcement data.
     *
     * @throws \RuntimeException on file, size, or API errors
     */
    public function parseFromPath(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF-Datei nicht gefunden: {$pdfPath}");
        }

        $bytes = filesize($pdfPath);
        if ($bytes > self::MAX_PDF_BYTES) {
            throw new \RuntimeException(
                sprintf('PDF zu groß (%.1f MB). Maximum: %d MB.', $bytes / 1_048_576, self::MAX_PDF_BYTES / 1_048_576)
            );
        }

        return $this->extractWithClaude($pdfPath);
    }

    // ── Claude extraction ───────────────────────────────────────────────────

    private function extractWithClaude(string $pdfPath): array
    {
        $apiKey = env('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException('ANTHROPIC_API_KEY nicht konfiguriert.');
        }

        $pdfBase64 = base64_encode(file_get_contents($pdfPath));

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->withOptions(['verify' => !env('CRAWLER_SSL_VERIFY_DISABLE', false)])
          ->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 16384,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    [
                        'type'   => 'document',
                        'source' => [
                            'type'       => 'base64',
                            'media_type' => 'application/pdf',
                            'data'       => $pdfBase64,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => $this->buildPrompt(),
                    ],
                ],
            ]],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Claude API Fehler ' . $response->status() . ': ' . $response->body());
        }

        $raw        = $response->json('content.0.text', '');
        $stopReason = $response->json('stop_reason', '');

        // Claude truncated the response — max_tokens too low
        if ($stopReason === 'max_tokens') {
            \Illuminate\Support\Facades\Log::warning('AusschreibungParser: Antwort abgeschnitten (max_tokens erreicht)', [
                'raw_length' => strlen($raw),
            ]);
        }

        if (preg_match('/```json\s*([\s\S]+?)\s*```/s', $raw, $m)) {
            $json = $m[1];
        } elseif (preg_match('/(\{[\s\S]+\})/s', $raw, $m)) {
            $json = $m[1];
        } else {
            \Illuminate\Support\Facades\Log::error('AusschreibungParser: Kein JSON in Antwort', [
                'raw_preview' => substr($raw, 0, 500),
            ]);
            throw new \RuntimeException(
                'Claude hat kein JSON zurückgegeben. stop_reason=' . $stopReason
                . ' Vorschau: ' . substr($raw, 0, 200)
            );
        }

        $data = json_decode($json, true);
        if ($data === null) {
            \Illuminate\Support\Facades\Log::error('AusschreibungParser: JSON-Syntaxfehler', [
                'error'       => json_last_error_msg(),
                'json_length' => strlen($json),
                'json_end'    => substr($json, -300),  // Die letzten 300 Zeichen — oft sieht man hier den Abbruch
            ]);
            throw new \RuntimeException(
                'JSON-Syntaxfehler: ' . json_last_error_msg()
                . ' (JSON-Länge: ' . strlen($json) . ' Zeichen, stop_reason=' . $stopReason . ')'
            );
        }

        $data['_meta'] = [
            'parsed_at'      => now()->toIso8601String(),
            'parser_version' => '2.0',
            'pdf_bytes'      => filesize($pdfPath),
        ];

        return $data;
    }

    private function buildPrompt(): string
    {
        return <<<'PROMPT'
Du analysierst eine deutsche Schwimmwettkampf-Ausschreibung (PDF) und extrahierst strukturierte Daten.
Gib ausschließlich ein JSON-Objekt zurück (kein Fließtext davor oder danach), das folgende Felder enthält.
Felder, die in der Ausschreibung nicht vorhanden sind, lasse weg oder setze null.
Beträge immer in Cent (ganzzahlig, z.B. 2300 für 23,00 €).
Zeiten immer im Format MM:SS,hh mit führenden Nullen (z.B. 00:28,45 oder 01:02,30).
Jahrgänge als Strings (z.B. "2008").
Disziplin-Kürzel: F=Freistil, B=Brust, R=Rücken, S=Schmetterling, L=Lagen.

JSON-Schema:
{
  "competition": {
    "name": "Vollständiger Name der Veranstaltung",
    "subtitle": "Untertitel / für welche Jahrgänge",
    "level": "dsv_dm|dsv_djm|nsv|shsv_lm|shsv_open|vereins",
    "date_from": "YYYY-MM-DD",
    "date_to": "YYYY-MM-DD",
    "eligible_age_groups": "z.B. 2008-2012"
  },
  "venue": {
    "name": "Name der Wettkampfstätte",
    "street": "Straße und Hausnummer",
    "city": "Stadt",
    "zip": "PLZ oder null",
    "pool_length_m": 50,
    "lanes_heats": 10,
    "lanes_finals": 8,
    "water_depth_m": 3.0,
    "water_temp_c": 26,
    "lane_ropes": "Art der Leinen",
    "timing": "elektronisch|halbautomatisch|handzeit",
    "warmup_pool": { "length_m": 50, "depth_m": 2.1, "temp_c": 26 }
  },
  "organizer": {
    "veranstalter": "Name des Veranstalters",
    "ausrichter": "Name des Ausrichters (kann gleich sein)"
  },
  "deadlines": [
    {
      "date": "YYYY-MM-DD",
      "time": "HH:MM",
      "type": "meldeschluss_einzel|meldeschluss_staffel|eingangsbestaetigung|meldebestaetigung|veroffentlichung_meldeergebnis|meldegeld_zahlung|beanstandungen|warmup|sonstiges",
      "description": "Kurzbeschreibung"
    }
  ],
  "entry": {
    "contact_name": "Name Meldeservice",
    "contact_email": "E-Mail für Meldungen",
    "format": "DSV-Standard 7",
    "fee_individual_cents": 2300,
    "fee_relay_cents": null,
    "payment_deadline": "YYYY-MM-DD",
    "payment_iban": "DE...",
    "payment_bic": "HELADEF1KAS",
    "payment_bank": "Name der Bank",
    "payment_reference": "Verwendungszweck"
  },
  "kampfgericht": {
    "note": "Allgemeine Angabe zur Bestellung des Kampfgerichts",
    "special": ["kein Zielgericht", "weitere Besonderheiten als Array"],
    "contacts": [
      { "role": "Rolle/Funktion", "name": "Name", "email": "E-Mail oder null" }
    ]
  },
  "enm": {
    "cases": [
      {
        "trigger": "no_show_heats|no_show_final|no_show_800_1500|sonstiges",
        "description": "Vollständige Beschreibung",
        "amount_cents": 5000,
        "waiver_condition": "Bedingung für Erlass (z.B. Abmeldung bis Vortag 18:00 Uhr) oder null"
      }
    ]
  },
  "accreditation": {
    "initial_coaches": 2,
    "initial_athletes": 5,
    "additional_per_athletes": 5,
    "additional_count": 1,
    "extra_card_fee_cents": 5000,
    "lost_card_fee_cents": 5000,
    "notes": "Weitere Hinweise (z.B. Physiotherapeuten, LSV-Akkreditierungen)"
  },
  "special_rules": [
    {
      "category": "einschwimmen|schwimmbekleidung|videoaufnahmen|laermschutz|doping|haftung|datenschutz|geraete|sonstiges",
      "title": "Titel der Regelung",
      "text": "Vollständiger Regeltext",
      "is_deviation_from_wb": true
    }
  ],
  "qualification": {
    "period_from": "YYYY-MM-DD",
    "period_to": "YYYY-MM-DD",
    "pool_type": "50m|25m|beide",
    "series": "Road to DJM",
    "rudolph_min": 4,
    "note": "Weitere Hinweise zur Qualifikation"
  },
  "start_quotas": {
    "2012": { "50m": 35, "100m": 30, "200m": 30, "400m": 20, "800m_1500m": 15 },
    "2011": { "50m": 30, "100m": 25, "200m": 25, "400m": 15, "800m_1500m": 15 }
  },
  "qualifying_times": {
    "M": {
      "2008": {
        "50F": "00:24,30", "100F": "00:53,30", "200F": "01:57,30",
        "400F": "04:07,00", "800F": "08:30,00", "1500F": "16:20,00",
        "50B": "00:30,50", "100B": "01:07,90", "200B": "02:28,00",
        "50R": "00:28,20", "100R": "01:00,90", "200R": "02:13,50",
        "50S": "00:26,00", "100S": "00:58,00", "200S": "02:11,00",
        "200L": "02:12,50", "400L": "04:42,00"
      }
    },
    "W": {
      "2008": { "50F": "00:27,20" }
    }
  },
  "relay_qualification": [
    {
      "stroke": "F|L",
      "gender": "W|M|Mixed",
      "min_time": "04:10,00",
      "top_n": 16
    }
  ],
  "schedule": [
    {
      "date": "YYYY-MM-DD",
      "session": 1,
      "type": "vorlauf|finale|entscheidung",
      "start_time": "08:30",
      "events": [
        { "wk": "01", "distance": 200, "stroke": "S", "gender": "W", "round": "Vorlauf" }
      ]
    }
  ]
}

Gib nur das JSON-Objekt zurück, eingewickelt in einen ```json ... ``` Code-Block.
PROMPT;
    }

    // ── DB-mapping helpers (called by controller) ───────────────────────────

    /**
     * Map parsed announcement data to Competition model fields.
     */
    public function mapToCompetitionFields(array $data): array
    {
        $fields = [];

        if (!empty($data['competition']['name'])) {
            $fields['name'] = $data['competition']['name'];
        }
        if (!empty($data['competition']['level'])) {
            $fields['level'] = $data['competition']['level'];
        }
        if (!empty($data['competition']['date_from'])) {
            $fields['date'] = $data['competition']['date_from'];
        }
        if (!empty($data['competition']['date_to'])) {
            $fields['date_end'] = $data['competition']['date_to'];
        }
        if (!empty($data['organizer']['veranstalter'])) {
            $fields['organizer'] = $data['organizer']['veranstalter'];
        }
        if (!empty($data['organizer']['ausrichter'])) {
            $fields['ausrichter'] = $data['organizer']['ausrichter'];
        }
        if (!empty($data['venue']['city'])) {
            $fields['location'] = trim(($data['venue']['city'] ?? ''));
        }

        $meldeschluss = collect($data['deadlines'] ?? [])
            ->firstWhere('type', 'meldeschluss_einzel');
        if ($meldeschluss && !empty($meldeschluss['date'])) {
            $fields['meldeschluss'] = $meldeschluss['date'];
        }

        if (!empty($data['venue'])) {
            $fields['venue_details'] = $data['venue'];
        }
        if (!empty($data['kampfgericht'])) {
            $fields['kampfgericht'] = $data['kampfgericht'];
        }
        if (!empty($data['entry']) || !empty($data['deadlines'])) {
            $fields['contact_info'] = array_filter([
                'entry'     => $data['entry'] ?? null,
                'deadlines' => $data['deadlines'] ?? [],
            ]);
        }

        $fields['announcement_data'] = $data;

        return $fields;
    }

    /**
     * Extract qualifying times as competition_events-compatible rows.
     * Returns array of ['discipline','distance','gender','age_group','qualifying_time_ms'].
     */
    public function extractQualifyingTimes(array $data): array
    {
        $rows = [];
        $disciplineMap = [
            'F' => 'F', 'B' => 'B', 'R' => 'R',
            'S' => 'S', 'L' => 'L',
        ];
        $genderMap = ['M' => 'M', 'W' => 'F'];

        foreach ($data['qualifying_times'] ?? [] as $genderCode => $yearGroups) {
            $gender = $genderMap[$genderCode] ?? $genderCode;
            foreach ($yearGroups as $year => $disciplines) {
                foreach ($disciplines as $key => $timeStr) {
                    if (!preg_match('/^(\d+)([FBRSL])$/', $key, $m)) continue;
                    $distance   = (int) $m[1];
                    $discipline = $disciplineMap[$m[2]] ?? null;
                    if (!$discipline || !$timeStr) continue;
                    $ms = $this->ds7TimeToMs($timeStr);
                    if ($ms <= 0) continue;

                    $rows[] = [
                        'discipline'         => $discipline,
                        'distance'           => $distance,
                        'gender'             => $gender,
                        'age_group'          => (string) $year,
                        'qualifying_time_ms' => $ms,
                    ];
                }
            }
        }

        return $rows;
    }

    private function ds7TimeToMs(string $t): int
    {
        $t     = trim(str_replace(',', '.', $t));
        $parts = explode(':', $t);
        return match (count($parts)) {
            3 => ((int)$parts[0] * 3_600_000) + ((int)$parts[1] * 60_000) + $this->secToMs($parts[2]),
            2 => ((int)$parts[0] * 60_000) + $this->secToMs($parts[1]),
            default => $this->secToMs($parts[0]),
        };
    }

    private function secToMs(string $s): int
    {
        [$sec, $centi] = array_pad(explode('.', $s, 2), 2, '0');
        return ((int)$sec * 1_000) + (int)str_pad(substr($centi, 0, 2), 2, '0') * 10;
    }
}
