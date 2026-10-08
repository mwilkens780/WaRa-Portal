@extends('layouts.legal')
@section('title', 'Hilfe zur Anmeldung')

@section('content')
{{-- Öffentlich (ohne Anmeldung): Einstieg, Passwort, App, Kalender-Abo – App\Support\HelpCatalog (public) --}}
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Hilfe zur Anmeldung</h1>
        <p class="mt-1 text-sm text-gray-600">Erste Schritte im WaRa-Portal. Nach der Anmeldung findest du alle Anleitungen unter <strong>Hilfe &amp; FAQ</strong> im Konto-Menü.</p>
    </div>

    <section class="space-y-2">
        @foreach($articles as $a)
            <details id="{{ $a['key'] }}" class="group rounded-xl border border-gray-100 bg-white shadow-sm" @if($loop->first) open @endif>
                <summary class="cursor-pointer list-none rounded-xl px-4 py-3 font-medium text-gray-900 hover:bg-gray-50">{{ $a['title'] }}</summary>
                <div class="rich-text help-article border-t border-gray-100 px-4 py-3">{!! $a['body'] !!}</div>
            </details>
        @endforeach
    </section>

    <section class="space-y-2">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Häufige Fragen</h2>
        @foreach($faq as $f)
            <details class="rounded-xl border border-gray-100 bg-white shadow-sm">
                <summary class="cursor-pointer list-none rounded-xl px-4 py-3 text-gray-900 hover:bg-gray-50">{{ $f['q'] }}</summary>
                <div class="rich-text help-article border-t border-gray-100 px-4 py-3"><p>{!! $f['a'] !!}</p></div>
            </details>
        @endforeach
    </section>

    <p class="text-sm text-gray-600">
        Klappt es trotzdem nicht? Wende dich an deinen Trainer oder die Geschäftsstelle.
        <a href="{{ route('login') }}" class="ml-1 font-semibold text-primary underline">Zur Anmeldung</a>
    </p>
</div>
@endsection
