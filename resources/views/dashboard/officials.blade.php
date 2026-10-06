@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

{{-- Startseite für Vorstand und Kampfrichter (Portal-Rolle). Andere Rollen sehen
     dieselbe Übersicht auf ihrem eigenen Dashboard. --}}
@section('content')
<div class="mt-2 space-y-6">
    <x-officials-panel />

    <x-ui.card title="Weiteres">
        <div class="flex flex-wrap gap-2">
            <x-ui.button variant="secondary" icon="inbox" :href="route('invitations.index')">Einladungen</x-ui.button>
            <x-ui.button variant="secondary" icon="calendar" :href="route('calendar.index')">Kalender</x-ui.button>
            @if(\App\Models\MenuPermission::can(auth()->user()->role, 'competitions'))
                <x-ui.button variant="secondary" icon="check-circle" :href="route('admin.competitions.index')">Wettkämpfe</x-ui.button>
            @endif
        </div>
    </x-ui.card>
</div>
@endsection
