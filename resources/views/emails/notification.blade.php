@extends('emails.layout')
@section('title', $heading)
@section('subtitle', $heading)

@section('content')
    @if($greetingName)
        <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">Hallo {{ $greetingName }},</p>
    @endif

    @foreach($paragraphs as $paragraph)
        <p style="margin:0 0 14px; font-size:15px; line-height:1.6;">{{ $paragraph }}</p>
    @endforeach

    @if(!empty($facts))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin:6px 0 20px;">
            <tr><td style="padding:14px 16px;">
                @foreach($facts as $label => $value)
                    <p style="margin:0 0 6px; font-size:14px; line-height:1.5; color:#374151;">
                        <span style="color:#6b7280;">{{ $label }}:</span>
                        <strong>{{ $value }}</strong>
                    </p>
                @endforeach
            </td></tr>
        </table>
    @endif

    @if($actionUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0 20px;">
            <tr>
                <td style="background-color:#1B5EAB; border-radius:8px;">
                    <a href="{{ $actionUrl }}"
                       style="display:inline-block; padding:12px 24px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">
                        {{ $actionLabel ?? 'Im Portal ansehen' }}
                    </a>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:0; font-size:15px; line-height:1.6;">
        Sportliche Grüße<br>
        SG Wasserratten Norderstedt
    </p>
@endsection

@if($footnote)
    @section('footer-note')
        {{ $footnote }}
    @endsection
@endif
