@extends('emails.layout')
@section('title', 'Willkommen im WaRa-Portal')
@section('subtitle', 'Dein Zugang zum Portal')

@section('content')
    <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
        Hallo {{ $user->firstname }},
    </p>

    @if($isResend)
        <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
            für dich gibt es einen Zugang zum WaRa-Portal. Hier sind deine Zugangsdaten noch einmal –
            falls du die erste Mail nicht mehr findest oder dein Passwort nie gesetzt hast.
        </p>
    @else
        <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
            schön, dass du dabei bist. Im WaRa-Portal findest du deine Trainingszeiten, Wettkämpfe,
            Ergebnisse und Bestzeiten an einer Stelle.
        </p>
    @endif

    <p style="margin:0 0 8px; font-size:15px; line-height:1.6;">
        Setz dir zuerst dein persönliches Passwort:
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
        <tr>
            <td style="background-color:#1B5EAB; border-radius:8px;">
                <a href="{{ $setupUrl }}"
                   style="display:inline-block; padding:12px 24px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                    Passwort jetzt setzen
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px; font-size:13px; line-height:1.6; color:#6b7280;">
        Der Link gilt 14 Tage und lässt sich einmal verwenden. Falls der Knopf nicht funktioniert,
        kopiere diese Adresse in deinen Browser:<br>
        <span style="word-break:break-all; color:#1B5EAB;">{{ $setupUrl }}</span>
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin:0 0 20px;">
        <tr>
            <td style="padding:14px 16px;">
                <p style="margin:0 0 6px; font-size:13px; color:#6b7280;">Dein Benutzername ist deine E-Mail-Adresse:</p>
                <p style="margin:0; font-size:15px; font-weight:600;">{{ $user->email }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
        Danach meldest du dich hier an:
        <a href="{{ $portalUrl }}" style="color:#1B5EAB;">{{ $portalUrl }}</a>
    </p>

    <p style="margin:0; font-size:15px; line-height:1.6;">
        Sportliche Grüße<br>
        SG Wasserratten Norderstedt
    </p>
@endsection

@section('footer-note')
    Ist der Link abgelaufen? Dann nutze auf der Anmeldeseite „Passwort vergessen“ –
    du bekommst sofort einen neuen.
@endsection
