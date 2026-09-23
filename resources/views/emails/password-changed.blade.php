@extends('emails.layout')
@section('title', 'Passwort geändert')
@section('subtitle', 'Sicherheitshinweis')

@section('content')
    <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
        Hallo {{ $user->firstname }},
    </p>

    <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
        das Passwort deines Zugangs zum WaRa-Portal wurde am {{ $changedAt }} geändert.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:8px; margin:0 0 20px;">
        <tr>
            <td style="padding:14px 16px;">
                <p style="margin:0; font-size:14px; line-height:1.6; color:#991b1b;">
                    <strong>Warst du das nicht?</strong> Dann melde dich bitte umgehend bei deinem Trainer
                    oder beim Vorstand, damit wir den Zugang sperren können.
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
        Zum Portal:
        <a href="{{ $portalUrl }}" style="color:#1B5EAB;">{{ $portalUrl }}</a>
    </p>

    <p style="margin:0; font-size:15px; line-height:1.6;">
        Sportliche Grüße<br>
        SG Wasserratten Norderstedt
    </p>
@endsection

@section('footer-note')
    Diese Nachricht dient deiner Sicherheit und lässt sich deshalb nicht abbestellen.
@endsection
