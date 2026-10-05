<?php

namespace App\Services\Competition;

use App\Models\Competition;

/**
 * Wettkampfdefinitionsliste (*-Wk.DSV7 / *-Wk.DSV8) nach DSV-Standard, Kapitel 5.1.
 *
 * Für eigene Veranstaltungen: Aus den Wertungen im Portal und den Kopfdaten
 * einer eingelesenen Ausschreibung (Ort, Ausrichter, Meldeadresse, Bank)
 * entsteht die Datei, die Vereine in ihre Meldesoftware laden.
 *
 * DSV8 ergänzt den Kontoinhaber in BANKVERBINDUNG und das Element LASTSCHRIFT.
 */
class DefinitionsdateiGenerator
{
    public function generate(Competition $competition, ?int $version = null): string
    {
        $competition->load(['events' => fn($q) => $q->reorder()->inProgramOrder()]);
        $version ??= DsvWriter::versionFor($competition);
        $h = $competition->dsv_header_data ?? [];
        $w = new DsvWriter($version);

        $w->add('FORMAT', ['Wettkampfdefinitionsliste', $version]);
        $w->add('ERZEUGER', ['WaRa-Portal', '1.0', config('mail.from.address') ?: 'portal@wasserratten.de']);
        $w->add('VERANSTALTUNG', [
            $competition->name,
            $competition->location ?? '',
            DsvWriter::poolLength($competition),
            DsvWriter::timing($competition),
        ]);
        $w->add('VERANSTALTUNGSORT', [
            $h['venue_name'] ?? ($competition->location ?? ''),
            $h['venue_street'] ?? '', $h['venue_plz'] ?? '', $h['venue_city'] ?? '',
            'GER', $h['venue_phone'] ?? '', '', '',
        ]);
        $w->add('AUSSCHREIBUNGIMNETZ', [$h['announcement_url'] ?? '']);
        $w->add('VERANSTALTER', [$competition->organizer ?: DsvWriter::clubName()]);
        $w->add('AUSRICHTER', [
            $h['ausrichter_name'] ?? DsvWriter::clubName(),
            $h['ausrichter_person'] ?? '', $h['ausrichter_street'] ?? '', $h['ausrichter_plz'] ?? '',
            $h['ausrichter_city'] ?? '', 'GER', $h['ausrichter_phone'] ?? '', '', $h['ausrichter_email'] ?? '',
        ]);
        $w->add('MELDEADRESSE', [
            $h['melde_person'] ?? '', $h['melde_street'] ?? '', $h['melde_plz'] ?? '', $h['melde_city'] ?? '',
            'GER', $h['melde_phone'] ?? '', '', $h['melde_email'] ?? '',
        ]);
        $w->add('MELDESCHLUSS', [
            $competition->meldeschluss?->format('d.m.Y') ?? ($h['meldeschluss_date'] ?? ''),
            $h['meldeschluss_time'] ?? '',
        ]);

        // LASTSCHRIFT und BANKVERBINDUNG schließen sich aus (DSV8)
        if ($version >= 8 && ($h['lastschrift'] ?? '') === 'J') {
            $w->add('LASTSCHRIFT', ['J']);
        } elseif (!empty($h['bank_iban'])) {
            $bank = [$h['bank_recipient'] ?? '', $h['bank_iban'], $h['bank_bic'] ?? ''];
            if ($version >= 8) $bank[] = $h['bank_holder'] ?? '';
            $w->add('BANKVERBINDUNG', $bank);
        }
        if (!empty($h['besonderes'])) {
            $w->add('BESONDERES', [$h['besonderes']]);
        }

        foreach (DsvWriter::abschnitte($competition) as $a) {
            $w->add('ABSCHNITT', [$a['nr'], $a['date'], $a['einlass'], $a['kari'], $a['start'], $a['relative']]);
        }

        $wettkaempfe = DsvWriter::wettkaempfe($competition);
        foreach ($wettkaempfe as $wk) {
            $w->add('WETTKAMPF', [
                $wk['nr'], $wk['art'] ?: 'E', $wk['abschnitt'], $wk['starter'], $wk['strecke'],
                $wk['technik'], $wk['ausuebung'] ?: 'GL', $wk['geschlecht'], $wk['bestenliste'] ?: 'SW',
                $wk['quali_nr'] ?? '', $wk['quali_art'] ?? '',
            ]);
        }

        // WertungsIDs: aus der Ausschreibung, sonst fortlaufend (eindeutig je Veranstaltung)
        $used   = $competition->events->pluck('dsv_wertungs_id')->filter()->all();
        $nextId = $used ? max($used) + 1 : 1;
        $fees   = [];

        foreach ($competition->events as $e) {
            $art = $wettkaempfe[$e->event_number]['art'] ?? 'E';
            $wid = $e->dsv_wertungs_id ?: $nextId++;
            $typ = ($e->age_min ?? $e->age_max ?? 9999) >= 1900 ? 'JG' : 'AK';
            $min = $e->age_min ?? 0;
            $max = $e->age_max ?? ($e->age_min ? '' : 9999);

            $w->add('WERTUNG', [
                $e->event_number, $art, $wid, $typ, $min, $max,
                DsvWriter::gender($e->gender), $e->age_group ?: 'Offene Wertung',
            ]);

            if ($e->qualifying_time_ms > 0) {
                $w->add('PFLICHTZEIT', [
                    $e->event_number, $art, $typ, $min, $max,
                    DsvWriter::time($e->qualifying_time_ms), DsvWriter::gender($e->gender),
                ]);
            }
            if ($e->meldegeld > 0) {
                $fees[$e->event_number] = (float) $e->meldegeld;
            }
        }

        // Meldegeld je Wettkampf; Pauschalen aus der Ausschreibung bleiben erhalten
        foreach ($h['flat_fees'] ?? [] as $fee) {
            $type = array_search($fee['type'] ?? '', \App\Services\Dsv7Parser::FLAT_FEE_TYPES, true);
            // Teilnehmermeldegeld und Abschnittspauschale gibt es erst ab DSV8
            if ($type && ($version >= 8 || !in_array($type, ['TEILNEHMERMELDEGELD', 'ABSCHNITTSPAUSCHALE'], true))) {
                $w->add('MELDEGELD', [$type, DsvWriter::amount((float) $fee['amount']), '']);
            }
        }
        foreach ($fees as $nr => $amount) {
            $w->add('MELDEGELD', ['WKMELDEGELD', DsvWriter::amount($amount), $nr]);
        }

        $w->add('DATEIENDE');

        return $w->content();
    }
}
