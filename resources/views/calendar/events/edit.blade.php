@extends('layouts.app')
@section('title', 'Termin bearbeiten')
@section('page-title', 'Termin bearbeiten')

@php
    $audiences = collect($types)->map(fn($t) => $t['audience'])->all();
@endphp

@section('content')
<div class="mt-2 max-w-3xl space-y-6">
    <form method="POST" action="{{ route('calendar.events.update', $calendarEvent) }}" class="space-y-6"
          x-data="{ type: {{ \Illuminate\Support\Js::from(old('type', $calendarEvent->type)) }}, audiences: {{ \Illuminate\Support\Js::from($audiences) }},
                    get audience() { return this.audiences[this.type] ?? null } }">
        @csrf @method('PUT')

        @if($errors->any())
            <x-ui.alert tone="error">Bitte die markierten Felder prüfen.</x-ui.alert>
        @endif

        <x-ui.card title="Termin">
            <div class="space-y-4">
                @include('calendar.events._fields')
            </div>
        </x-ui.card>

        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button type="submit">Speichern</x-ui.button>
            <x-ui.button variant="secondary" :href="$calendarEvent->hasInvitations() ? route('calendar.events.show', $calendarEvent) : route('calendar.index')">Abbrechen</x-ui.button>
        </div>
    </form>

    <form method="POST" action="{{ route('calendar.events.destroy', $calendarEvent) }}"
          data-confirm="Termin „{{ $calendarEvent->title }}“ löschen? Einladungen, Rückmeldungen und Anhänge gehen verloren." data-confirm-label="Löschen" data-confirm-danger>
        @csrf @method('DELETE')
        <x-ui.button type="submit" variant="danger">Termin löschen</x-ui.button>
    </form>
</div>
@endsection
