@extends('layouts.app')
@section('title', 'Termin anlegen')
@section('page-title', 'Termin anlegen')

@php
    $audiences  = collect($types)->map(fn($t) => $t['audience'])->all();
    $initial    = old('type', $defaultType);
@endphp

@section('content')
<form method="POST" action="{{ route('calendar.events.store') }}" class="mt-2 max-w-3xl space-y-6"
      x-data="{ type: {{ \Illuminate\Support\Js::from($initial) }}, audiences: {{ \Illuminate\Support\Js::from($audiences) }},
                get audience() { return this.audiences[this.type] ?? null } }">
    @csrf
    <input type="hidden" name="return_to" value="{{ url()->previous() }}">

    @if($errors->any())
        <x-ui.alert tone="error">Bitte die markierten Felder prüfen.</x-ui.alert>
    @endif

    <x-ui.card title="Termin">
        <div class="space-y-4">
            @include('calendar.events._fields')
        </div>
    </x-ui.card>

    <div x-show="audience" x-cloak>
        <x-ui.card title="Einladen">
            <p class="text-sm text-gray-600 mb-4">
                Die Eingeladenen bekommen eine Mail mit Agenda und Link zur Anmeldung. Agenda, Anhänge und
                Protokolle sehen nur Eingeladene; im Kalender steht der Termin für alle ohne Details.
                Anhänge und frühere Protokolle fügst du nach dem Speichern auf der Terminseite hinzu.
            </p>
            @include('calendar.events._invite')
        </x-ui.card>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <x-ui.button type="submit">
            <span x-text="audience ? 'Speichern und einladen' : 'Speichern'">Speichern</span>
        </x-ui.button>
        <x-ui.button variant="secondary" :href="route('calendar.index')">Abbrechen</x-ui.button>
    </div>
</form>
@endsection
