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
        $released = 0;
        // Wer absagt, faehrt auch nicht mit - Busplatz und Mitfahrt freigeben.
        // Ein eigenes Fahrgemeinschafts-Angebot entfaellt; die Mitfahrer werden frei.
        if (!$attending) {
            $update['bus_booked']      = false;
            $update['carpool_ride_id'] = null;
            $update['carpool_seats']   = null;
            $released = CompetitionSignupResponse::where('carpool_ride_id', $response->id)->update(['carpool_ride_id' => null]);
        }
        if ($request->offer_overnight) {
            $update['wants_overnight'] = (bool) ($data['wants_overnight'] ?? false);
        }
        if ($request->offer_dinner) {
            $update['wants_dinner'] = (bool) ($data['wants_dinner'] ?? false);
        }
        // Fahrgemeinschaft bieten nur Eltern an - mit Namen, damit Mitfahrer wissen, wer faehrt
        if ($byParent && $attending && array_key_exists('carpool_seats', $data)) {
            $seats  = (int) ($data['carpool_seats'] ?? 0);
            $booked = $response->carpoolPassengers()->count();
            if ($seats < $booked) {
                return [false, ($booked === 1 ? "Es fährt schon ein Kind mit" : "Es fahren schon {$booked} Kinder mit") . " – weniger Plätze gehen erst, wenn jemand storniert."];
            }
            $update['carpool_seats']         = $seats ?: null;
            $update['carpool_offered_by_id'] = $seats ? auth()->id() : null;
            $update['carpool_note']          = $seats ? (($data['carpool_note'] ?? null) ?: null) : null;
        }

        $response->update($update);

        // Trainer informieren - egal ob Kind oder Eltern geantwortet haben
        $this->mailer->signupResponded($response->fresh(['user', 'signupRequest.competition']));

        $label = $attending ? 'Zusage' : 'Absage';
        $msg   = $byParent ? "{$label} für {$swimmer->firstname} gespeichert." : "{$label} gespeichert.";
        if ($released) $msg .= " Das Fahrgemeinschafts-Angebot entfällt – " . ($released === 1 ? "ein Mitfahrer ist" : "{$released} Mitfahrer sind") . " wieder frei.";
        return [true, $msg];
    }

    /**
     * Platz in einer Fahrgemeinschaft buchen. Gesperrt wie beim Bus, damit der
     * letzte Platz nicht doppelt vergeben wird. Bus und Fahrgemeinschaft
     * schliessen sich aus: ein gebuchter Busplatz wird dabei frei.
     *
     * @return array{0: bool, 1: string}
     */
    public function bookCarpool(CompetitionSignupRequest $request, User $swimmer, int $offerId, bool $byParent = false): array
    {
        if (!$request->isActive()) {
            return [false, 'Diese Anmeldeabfrage ist nicht mehr aktiv.'];
        }

        return DB::transaction(function () use ($request, $swimmer, $offerId, $byParent) {
            $offer = CompetitionSignupResponse::whereKey($offerId)->lockForUpdate()->first();
            $response = $this->responseFor($request, $swimmer);
            $wer = $byParent ? " für {$swimmer->firstname}" : '';

            if (!$response || !$response->isAttending()) {
                return [false, $byParent ? "{$swimmer->firstname} ist noch nicht als Teilnehmer angemeldet." : 'Du hast dich noch nicht als Teilnehmer angemeldet.'];
            }
            if (!$offer || $offer->competition_signup_request_id !== $request->id || !$offer->isAttending() || !$offer->carpool_seats) {
                return [false, 'Dieses Angebot gibt es nicht mehr.'];
            }
            if ($offer->id === $response->id) {
                return [false, 'Das ist das eigene Angebot.'];
            }
            if ($response->carpool_ride_id !== $offer->id && $offer->carpoolSeatsRemaining() <= 0) {
                return [false, 'Leider sind in dieser Fahrgemeinschaft keine Plätze mehr frei.'];
            }

            $hadBus = $response->bus_booked;
            $response->update(['carpool_ride_id' => $offer->id, 'bus_booked' => false]);
            $offer->load('carpoolOfferedBy', 'user');

            return [true, "Mitfahrt{$wer} bei {$offer->carpoolDriverLabel()} gebucht." . ($hadBus ? ' Der Busplatz ist dafür freigegeben.' : '')];
        });
    }

    /** @return array{0: bool, 1: string} */
    public function cancelCarpool(CompetitionSignupRequest $request, User $swimmer, bool $byParent = false): array
    {
        $response = $this->responseFor($request, $swimmer);
        if (!$response?->carpool_ride_id) {
            return [false, 'Es ist keine Mitfahrt gebucht.'];
        }
        $response->update(['carpool_ride_id' => null]);

        return [true, $byParent ? "Mitfahrt für {$swimmer->firstname} storniert." : 'Mitfahrt storniert.'];
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

            // Bus und Fahrgemeinschaft schliessen sich aus
            $hadRide = !$response->bus_booked && $response->carpool_ride_id;
            $response->update(['bus_booked' => !$response->bus_booked] + ($hadRide ? ['carpool_ride_id' => null] : []));

            $wer = $byParent ? " für {$swimmer->firstname}" : '';
            return [true, $response->bus_booked
                ? "Busplatz{$wer} gebucht." . ($hadRide ? ' Die Mitfahrt in der Fahrgemeinschaft ist dafür storniert.' : '')
                : "Busplatz{$wer} storniert."];
        });
    }

    private function responseFor(CompetitionSignupRequest $request, User $swimmer): ?CompetitionSignupResponse
    {
        return CompetitionSignupResponse::where('competition_signup_request_id', $request->id)
            ->where('user_id', $swimmer->id)
            ->first();
    }
}
