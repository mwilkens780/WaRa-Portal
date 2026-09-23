@extends('emails.layout')
@section('title', 'Passwort neu setzen')
@section('subtitle', 'Passwort zurücksetzen')

@section('content')
    <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
        Hallo {{ $user->firstname }},
    </p>

    @if($byAdmin)
        <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
            {{ $byName ? $byName . ' hat' : 'Jemand aus dem Trainerteam hat' }} dein Passwort für das
            WaRa-Portal zurückgesetzt. Dein altes Passwort gilt nicht mehr. Über den Knopf unten setzt
            du dir ein neues.
        </p>
    @else
        <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
            du hast ein neues Passwort für das WaRa-Portal angefordert.
        </p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
        <tr>
            <td style="background-color:#1B5EAB; border-radius:8px;">
                <a href="{{ $resetUrl }}"
                   style="display:inline-block; padding:12px 24px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                    Neues Passwort setzen
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px; font-size:13px; line-height:1.6; color:#6b7280;">
        Der Link gilt eine Stunde und lässt sich einmal verwenden. Falls der Knopf nicht funktioniert,
        kopiere diese Adresse in deinen Browser:<br>
        <span style="word-break:break-all; color:#1B5EAB;">{{ $resetUrl }}</span>
    </p>

    @if(!$byAdmin)
        <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#6b7280;">
            Hast du das nicht angefordert? Dann ignoriere diese Mail – dein bisheriges Passwort
            bleibt gültig, solange du den Link nicht benutzt.
        </p>
    @endif

    <p style="margin:0; font-size:15px; line-height:1.6;">
        Sportliche Grüße<br>
        SG Wasserratten Norderstedt
    </p>
@endsection
