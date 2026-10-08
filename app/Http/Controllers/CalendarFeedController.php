<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Competition;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\CalendarFeed;
use App\Services\CalendarScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Kalender in Outlook, Apple und Google (Martin, 08.10.2026):
 *  - Abo: persönlicher, geheimer Link (webcal), wird regelmäßig abgeholt
 *  - Einzel-Export: ein Eintrag als .ics-Datei ("In meinen Kalender")
 */
class CalendarFeedController extends Controller
{
    public function __construct(private CalendarFeed $feed, private CalendarScope $scope) {}

    /** Abo-Seite mit Link und Anleitung */
    public function show(Request $request)
    {
        $user  = $request->user();
        $https = route('calendar.feed', $user->calendarToken());

        return view('calendar.subscribe', [
            'httpsUrl'  => $https,
            // webcal:// – das iPhone versucht damit zuerst https; webcals:// kennt Safari nicht ("Adresse ungültig")
            'webcalUrl' => preg_replace('#^https?://#', 'webcal://', $https),
            'seesAll'   => $this->scope->seesAll($user),
            'hasKids'   => $user->children()->where('active', true)->exists(),
        ]);
    }

    /** Neuer Link – der alte funktioniert danach nicht mehr */
    public function renew(Request $request)
    {
        $request->user()->calendarToken(renew: true);

        return redirect()->route('calendar.subscribe')
            ->with('success', 'Neuer Abo-Link erstellt. Der alte Link funktioniert nicht mehr – bitte im Kalender neu abonnieren.');
    }

    /** Der Feed selbst – ohne Anmeldung, der Schlüssel im Link weist aus */
    public function feed(string $token)
    {
        $user = strlen($token) >= 32 ? User::where('calendar_token', $token)->first() : null;
        abort_unless($user && $user->active && ($user->portal_active ?? true) && $user->canAccess('calendar'), 404);

        return response($this->feed->forUser($user), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="wara-kalender.ics"',
            'Cache-Control'       => 'private, max-age=900',
            'X-Robots-Tag'        => 'noindex',
        ]);
    }

    /** Ein Eintrag als Datei */
    public function export(Request $request, string $type, int $id)
    {
        $user = $request->user();

        $item = null;
        if ($type === 'training' && ($s = $this->scope->sessions($user)->whereKey($id)->first())) {
            $item = $this->feed->session($s, $user);
        } elseif ($type === 'wettkampf' && ($c = $this->scope->competitions($user)->whereKey($id)->first())) {
            $item = $this->feed->competition($c, $user);
        } elseif ($type === 'termin' && ($e = CalendarEvent::find($id)) && $this->scope->eventVisible($user, $e)) {
            $item = $this->feed->event($e);
        }
        abort_unless($item, 404);

        $name = Str::slug($item['summary']) ?: 'termin';

        return response($this->feed->single($item), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '.ics"',
        ]);
    }
}
