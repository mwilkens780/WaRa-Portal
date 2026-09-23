@extends('emails.layout')
@section('title', 'Testmail')
@section('subtitle', 'Prüfung des Mailversands')

@section('content')
    <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
        Diese Mail bestätigt, dass das WaRa-Portal E-Mails verschicken kann.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin:0 0 20px;">
        <tr><td style="padding:14px 16px; font-size:14px; line-height:1.8; color:#374151;">
            <strong>Versendet am:</strong> {{ $sentAt }}<br>
            <strong>Ausgelöst von:</strong> {{ $triggeredBy ? $triggeredBy->firstname . ' ' . $triggeredBy->lastname : 'System' }}<br>
            <strong>Versandweg:</strong> {{ $mailer }}@if($host) über {{ $host }}@endif<br>
            <strong>Absender:</strong> {{ $from }}
        </td></tr>
    </table>

    <p style="margin:0; font-size:14px; line-height:1.6; color:#6b7280;">
        Kommt diese Mail an, funktioniert der Versand. Steht im Betreff „[TEST an …]“, ist der
        Wartungsmodus aktiv und alle Mails gehen an die hinterlegte Testadresse.
    </p>
@endsection
