<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Services\Mailer;
use App\Support\MailTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(private Mailer $mailer) {}

    public function showChangeForm()
    {
        return view('auth.change-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [], ['password' => 'Passwort']);

        $user = $request->user();

        $user->update([
            'password'         => Hash::make($request->password),
            'initial_password' => null,   // ab jetzt gilt das eigene Passwort
        ]);

        // Neue Sitzungs-ID: Wer die alte kannte, kommt damit nicht weiter
        $request->session()->regenerate();

        // Sicherheitsmeldung: Wer die Aenderung nicht selbst vorgenommen hat,
        // erfaehrt hier davon und kann reagieren.
        $mail = new PasswordChangedMail($user);
        $this->mailer->send($user, MailTopic::ACCOUNT, $mail, $mail->defaultSubject());

        return back()->with('success', 'Passwort erfolgreich geändert. Eine Bestätigung ist an deine E-Mail-Adresse unterwegs.');
    }
}
