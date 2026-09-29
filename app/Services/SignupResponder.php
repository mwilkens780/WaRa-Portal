<?php

namespace App\Services;

use App\Models\CompetitionSignupRequest;
use App\Models\CompetitionSignupResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rueckmeldung und Busplatz zu einer Wettkampf-Abfrage - fuer den Schwimmer
 * selbst und fuer Eltern (viele Kinder haben kein Handy und keine Mail).
 *
 * Vorher hatten Schwimmer- und Eltern-Controller je eine eigene Fassung, die
 * auseinanderliefen: Eltern-Antworten informierten die Trainer nicht, eine
 * Absage behielt den Busplatz, und der letzte freie Platz konnte doppelt
 * vergeben werden.
 */
class SignupResponder
{
    public function __construct(private EventMailer $mailer) {}

    /**
     * @param array{status: string, note?: ?string, wants_overnight?: bool, wants_dinner?: bool, carpool_seats?: ?int} $data
     * @return array{0: bool, 1: string} [erfolgreich, Meldung]
     */
    public function respond(CompetitionSignupRequest $request, User $swimmer, array $data, bool $byParent = false): array
    {
        if (!$request->isActive()) {
            return [false, 'Diese Anmeldeabfrage ist nicht mehr aktiv.'];
        }

        $response = $this->responseFor($request, $swimmer);
        if (!$response) {
            return [false, $byParent
                ? "{$swimmer->firstname} ist nicht für diese Anmeldeabfrage eingeladen."
                : 'Du bist nicht für diese Anmeldeabfrage eingeladen.'];
        }

        $attending = $data['status'] === 'attending';
        $update = [
            'status'       => $data['status'],
            'note'         => $data['note'] ?? null,
            'responded_at' => now(),
        ];
        // Wer absagt, faehrt auch nicht mit - Busplatz wieder freigeben
        if (!$attending) {
            $update['bus_booked'] = false;
        }
        if ($request->offer_overnight) {
            $update['wants_overnight'] = (bool) ($data['wants_overnight'] ?? false);
        }
        if ($request->offer_dinner) {
            $update['wants_dinner'] = (bool) ($data['wants_dinner'] ?? false);
        }
        // Fahrgemeinschaft bieten nur Eltern an
        if ($byParent) {
            $update['carpool_seats'] = isset($data['carpool_seats']) ? (int) $data['carpool_seats'] : null;
        }

        $response->update($update);

        // Trainer informieren - egal ob Kind oder Eltern geantwortet haben
        $this->mailer->signupResponded($response->fresh(['user', 'signupRequest.competition']));

        $label = $attending ? 'Zusage' : 'Absage';
        return [true, $byParent ? "{$label} für {$swimmer->firstname} gespeichert." : "{$label} gespeichert."];
    }

    /** @return array{0: bool, 1: string} [erfolgreich, Meldung] */
    public function toggleBus(CompetitionSignupRequest $request, User $swimmer, bool $byParent = false): array
    {
        if (!$request->isActive()) {
            return [false, 'Diese Anmeldeabfrage ist nicht mehr aktiv.'];
        }
        if (!$request->bus_available) {
            return [false, 'Für diese Abfrage ist kein Bus verfügbar.'];
        }

        // Sperre auf die Abfrage: sonst koennen zwei gleichzeitige Buchungen
        // beide den letzten freien Platz bekommen
        return DB::transaction(function () use ($request, $swimmer, $byParent) {
            $request = CompetitionSignupRequest::whereKey($request->id)->lockForUpdate()->first();
            $response = $this->responseFor($request, $swimmer);

            if (!$response || !$response->isAttending()) {
                return [false, $byParent
                    ? "{$swimmer->firstname} ist noch nicht als Teilnehmer angemeldet."
                    : 'Du hast dich noch nicht als Teilnehmer angemeldet.'];
            }
            if (!$response->bus_booked && $request->busSeatsRemaining() <= 0) {
                return [false, 'Leider sind keine Plätze mehr frei.'];
            }

            $response->update(['bus_booked' => !$response->bus_booked]);

            $wer = $byParent ? " für {$swimmer->firstname}" : '';
            return [true, $response->bus_booked ? "Busplatz{$wer} gebucht." : "Busplatz{$wer} storniert."];
        });
    }

    private function responseFor(CompetitionSignupRequest $request, User $swimmer): ?CompetitionSignupResponse
    {
        return CompetitionSignupResponse::where('competition_signup_request_id', $request->id)
            ->where('user_id', $swimmer->id)
            ->first();
    }
}
