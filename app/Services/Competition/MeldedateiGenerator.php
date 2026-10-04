<?php

namespace App\Services\Competition;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionRelayEntry;
use App\Models\User;

/**
 * Vereinsmeldeliste (*-Me.DSV7 / *-Me.DSV8) nach DSV-Standard, Kapitel 5.2.
 *
 * Aufbau: Kopf, die Wettkämpfe der Ausschreibung, VEREIN, ANSPRECHPARTNER,
 * je Schwimmer PNMELDUNG mit seinen STARTPN, je Staffel STMELDUNG, STARTST
 * und STAFFELPERSON. Reine Staffelschwimmer stehen als PNMELDUNG ohne STARTPN.
 * Gemeldet wird je Wettkampfnummer, nicht je Wertung.
 *
 * DSV7 und DSV8 unterscheiden sich hier nur im Attribut Lastschrift am Ende
 * von VEREIN (nur DSV8).
 */
class MeldedateiGenerator
{
    public function generate(Competition $competition, ?User $contact = null, ?int $version = null): string
    {
        $competition->loadMissing('events');
        $version ??= DsvWriter::versionFor($competition);
        $w = new DsvWriter($version);

        $entries = CompetitionEntry::with(['user', 'competitionEvent'])
            ->where('competition_id', $competition->id)
            ->where('status', 'entered')
            ->get()
            ->filter(fn($e) => $e->user && $e->competitionEvent)
            ->groupBy('user_id');

        $relays = CompetitionRelayEntry::with(['members.user', 'competitionEvent'])
            ->where('competition_id', $competition->id)
            ->where('status', 'entered')
            ->get()
            ->filter(fn($r) => $r->competitionEvent);

        $w->add('FORMAT', ['Vereinsmeldeliste', $version]);
        $w->add('ERZEUGER', ['WaRa-Portal', '1.0', config('mail.from.address') ?: 'portal@wasserratten.de']);
        $w->add('VERANSTALTUNG', [
            $competition->name,
            $competition->location ?? '',
            DsvWriter::poolLength($competition),
            DsvWriter::timing($competition),
        ]);

        foreach (DsvWriter::abschnitte($competition) as $a) {
            $w->add('ABSCHNITT', [$a['nr'], $a['date'], $a['start'], $a['relative']]);
        }

        foreach (DsvWriter::wettkaempfe($competition) as $wk) {
            $w->add('WETTKAMPF', [
                $wk['nr'], $wk['art'] ?: 'E', $wk['abschnitt'], $wk['starter'], $wk['strecke'],
                $wk['technik'], $wk['ausuebung'] ?: 'GL', $wk['geschlecht'],
                $wk['quali_nr'] ?? '', $wk['quali_art'] ?? '',
            ]);
        }

        $verein = [DsvWriter::clubName(), DsvWriter::clubNumber() ?? '0', DsvWriter::LSV_SHSV, 'GER'];
        if ($version >= 8) {
            $verein[] = 'N'; // Lastschrift-Erlaubnis liegt dem Ausrichter nicht vor
        }
        $w->add('VEREIN', $verein);

        $w->add('ANSPRECHPARTNER', [
            DsvWriter::personName($contact?->lastname, $contact?->firstname),
            '', '', '', '', $contact?->mobile ?: ($contact?->phone ?? ''), '',
            $contact?->email ?? '',
        ]);

        // Alle Personen: Einzelstarter und reine Staffelschwimmer
        $persons = [];
        foreach ($entries as $userEntries) {
            $persons[$userEntries->first()->user_id] = $userEntries->first()->user;
        }
        foreach ($relays as $relay) {
            foreach ($relay->members as $m) {
                if ($m->user) $persons[$m->user_id] ??= $m->user;
            }
        }

        foreach ($persons as $userId => $user) {
            $own = $entries->get($userId, collect());
            $w->add('PNMELDUNG', [
                DsvWriter::personName($user->lastname, $user->firstname),
                $user->dsv_id ?: '0',
                $userId,                                            // Veranstaltungs-ID
                DsvWriter::gender($user->gender ?? $own->first()?->gender),
                $user->birth_date?->year ?? '',
                '', '', '', '', '',                                 // AK, Trainer, Nationalitäten
            ]);

            foreach ($own->sortBy(fn($e) => $e->competitionEvent->event_number)
                         ->unique(fn($e) => $e->competitionEvent->event_number) as $entry) {
                $w->add('STARTPN', [$userId, $entry->competitionEvent->event_number, DsvWriter::time($entry->entry_time_ms)]);
            }
        }

        $teamNo = [];
        foreach ($relays->sortBy(fn($r) => $r->competitionEvent->event_number) as $relay) {
            $event = $relay->competitionEvent;
            $nr    = $event->event_number;
            $teamNo[$nr] = ($teamNo[$nr] ?? 0) + 1;

            $min = $event->age_min ?? 0;
            $max = $event->age_max ?? ($event->age_min ? '' : 9999);
            // Jahrgänge sind vierstellig, Altersklassen (Masters) nicht
            $typ = ($event->age_min ?? $event->age_max ?? 9999) >= 1900 ? 'JG' : 'AK';
            $w->add('STMELDUNG', [$teamNo[$nr], $relay->id, $typ, $min, $max, '']);
            $w->add('STARTST', [$relay->id, $nr, DsvWriter::time($relay->entry_time_ms)]);

            foreach ($relay->members->sortBy('position') as $m) {
                if ($m->user) {
                    $w->add('STAFFELPERSON', [$relay->id, $nr, $m->user_id, $m->position]);
                }
            }
        }

        $w->add('DATEIENDE');

        return $w->content();
    }
}
