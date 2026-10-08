@extends('layouts.app')
@section('title', 'Kalender abonnieren')
@section('page-title', 'Kalender abonnieren')

@section('content')
<div class="mt-2 max-w-3xl space-y-6">
    <x-ui.alert tone="info">
        Mit diesem persönlichen Link erscheinen deine Termine in Outlook, im iPhone-Kalender oder im Google-Kalender (Android)
        und werden dort automatisch aktuell gehalten:
        {{ $seesAll ? 'Trainingseinheiten, Wettkämpfe, Termine und Einladungen aller Gruppen sowie die Vorstandstermine.'
                    : 'deine Trainingseinheiten, Wettkämpfe, Einladungen sowie Vereinstermine, Meldefristen und Ehrungen deiner Gruppe' . ($hasKids ? ' – auch die deiner Kinder.' : '.') }}
        Abgesagte Einheiten bleiben als „ABGESAGT“ stehen. Änderungen machst du weiter im Portal, nicht im Kalender.
    </x-ui.alert>

    <x-ui.card title="Dein Abo-Link">
        <div x-data="{ copied: false }" class="space-y-3">
            <label for="feed-url" class="block text-sm font-medium text-gray-700">Link (geheim halten – wer ihn hat, sieht deine Termine)</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input id="feed-url" type="text" readonly value="{{ $httpsUrl }}" x-ref="url" @focus="$event.target.select()"
                       class="w-full min-w-0 rounded-lg border border-gray-300 px-3 py-2 font-mono text-xs text-gray-700">
                <x-ui.button variant="secondary" @click="navigator.clipboard.writeText($refs.url.value); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? 'Kopiert' : 'Kopieren'">Kopieren</span>
                </x-ui.button>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button :href="$webcalUrl" icon="calendar">Auf diesem Gerät abonnieren</x-ui.button>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="So geht's">
        <div class="space-y-4 text-sm text-gray-700">
            <div>
                <h3 class="font-semibold text-gray-800">iPhone / iPad</h3>
                <p>„Auf diesem Gerät abonnieren“ antippen und „Abonnieren“ bestätigen. Unter Einstellungen → Kalender → Accounts lässt sich einstellen, wie oft aktualisiert wird (z. B. stündlich).</p>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800">Outlook (Windows, Mac, Web)</h3>
                <p>Link kopieren, dann im Kalender „Kalender hinzufügen“ → „Aus dem Internet abonnieren“ und den Link einfügen. Outlook aktualisiert etwa alle paar Stunden.</p>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800">Android (Google Kalender)</h3>
                <p>Link kopieren, am Computer <span class="break-all">calendar.google.com</span> öffnen → links bei „Weitere Kalender“ auf „+“ → „Per URL“ → Link einfügen. Der Kalender erscheint danach auch auf dem Handy (in der App unter Einstellungen den Kalender einschalten).
                   Google aktualisiert nur etwa alle 8 bis 24 Stunden. Schneller geht es mit der kostenlosen App <strong>ICSx⁵</strong>: dort den Link einfügen und das Intervall wählen.</p>
            </div>
            <p class="text-xs text-gray-500">Einzelne Termine kannst du auch direkt übernehmen: im Kalender beim Termin „In meinen Kalender“.</p>
        </div>
    </x-ui.card>

    <x-ui.card title="Link ersetzen">
        <p class="mb-3 text-sm text-gray-700">Wenn du den Link versehentlich weitergegeben hast: neuen Link erzeugen. Der alte funktioniert danach nicht mehr, du musst im Kalender neu abonnieren.</p>
        <form method="POST" action="{{ route('calendar.subscribe.renew') }}"
              data-confirm="Neuen Abo-Link erzeugen?" data-confirm-text="Der bisherige Link funktioniert danach nicht mehr." data-confirm-label="Neuen Link erzeugen">
            @csrf
            <x-ui.button type="submit" variant="secondary">Neuen Link erzeugen</x-ui.button>
        </form>
    </x-ui.card>
</div>
@endsection
