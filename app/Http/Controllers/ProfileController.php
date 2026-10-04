<?php

namespace App\Http\Controllers;

use App\Support\MailTopic;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('profile.index', [
            'user'        => $user,
            'mailTopics'  => MailTopic::groupedForRole($user->role),
            'mailPrefs'   => $user->mail_preferences ?? [],
            // Minderjaehrige mit Eltern im Portal: Eltern entscheiden (siehe User)
            'consentByParents' => $user->consentManagedByParents(),
            'wards'            => $user->wards(),
        ]);
    }

    /**
     * Mail-Einstellungen.
     *
     * Opt-in: Was nicht angehakt ist, wird auch nicht verschickt. Kontomails
     * stehen nicht zur Wahl - ohne sie kommt niemand an seinen Zugang.
     */
    public function updateMailPreferences(Request $request)
    {
        $user = auth()->user();

        $allowed = array_keys(MailTopic::forRole($user->role));
        $chosen  = [];

        foreach ($allowed as $topic) {
            if (MailTopic::isMandatory($topic)) continue;
            $chosen[$topic] = $request->boolean('topics.' . $topic);
        }

        $user->update(['mail_preferences' => $chosen]);

        $count = count(array_filter($chosen));

        return redirect()->route('profile.index')->with('success', $count === 0
            ? 'Gespeichert. Du bekommst nur noch Mails zu deinem Zugang.'
            : "Gespeichert. Du bekommst Mails zu {$count} Thema/Themen.");
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'firstname'           => ['required', 'string', 'max:100'],
            'lastname'            => ['required', 'string', 'max:100'],
            'email'               => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'email2'              => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'mobile'              => ['nullable', 'string', 'max:50'],
            'street'              => ['nullable', 'string', 'max:255'],
            'postal_code'         => ['nullable', 'string', 'max:20'],
            'city'                => ['nullable', 'string', 'max:100'],
            'opt_nutrition'       => ['boolean'],
            'opt_sports_medicine' => ['boolean'],
            'carpool_share_phone' => ['boolean'],
        ]);

        // Handynummer bei Fahrgemeinschaften: Voreinstellung fuer den Haken am Angebot
        $data['carpool_share_phone'] = $request->boolean('carpool_share_phone');

        if ($user->consentManagedByParents()) {
            // Entscheiden die Eltern - Formularwerte des Kindes nicht uebernehmen
            unset($data['opt_nutrition'], $data['opt_sports_medicine']);
        } else {
            $data['opt_nutrition']       = $request->boolean('opt_nutrition');
            $data['opt_sports_medicine'] = $request->boolean('opt_sports_medicine');
        }

        $user->update($data);

        return redirect()->route('profile.index')
            ->with('success', 'Profil gespeichert.');
    }

    /** Eltern setzen die Gesundheits-Einwilligungen ihres minderjaehrigen Kindes */
    public function updateWardConsents(Request $request, \App\Models\User $child)
    {
        abort_unless(auth()->user()->isGuardianOf($child), 403);

        $child->update([
            'opt_nutrition'       => $request->boolean('opt_nutrition'),
            'opt_sports_medicine' => $request->boolean('opt_sports_medicine'),
        ]);

        return redirect()->route('profile.index')
            ->with('success', "Einwilligungen für {$child->firstname} gespeichert.");
    }
}
