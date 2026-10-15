@php
    // Sportler sieht sich selbst; Eltern sehen hier ein Kind (ParentArea\DashboardController::childStats)
    $subject  = $subject ?? auth()->user();
    $asParent = $asParent ?? false;
    [$statsRouteName, $statsRouteBase] = $statsRoute ?? ['swimmer.stats', []];
    $pageTitle = $asParent ? 'Wertung: ' . $subject->firstname : 'Meine Wertung';

    // Kennzahlen mit Vorsaison (Martin, 15.10.2026: keine Gesamtpunkte, nur eigene Werte)
    $tiles = [
        ['key' => 'competitions',   'label' => 'Wettkämpfe',        'color' => 'text-primary'],
        ['key' => 'starts',         'label' => 'Starts',            'color' => 'text-primary'],
        ['key' => 'podiums',        'label' => 'Podestplätze',      'color' => 'text-amber-600'],
        ['key' => 'finals',         'label' => 'Finals',            'color' => 'text-primary'],
        ['key' => 'personal_bests', 'label' => 'Bestzeiten',        'color' => 'text-accent'],
        ['key' => 'club_records',   'label' => 'Vereinsrekorde',    'color' => 'text-green-700'],
    ];
@endphp
@extends('layouts.app')
@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('content')
<div class="mt-2 space-y-6">
    @if($asParent)
        <a href="{{ route('parent.dashboard') }}" class="inline-block text-sm text-gray-500 hover:text-primary">← Übersicht</a>
    @endif

    <form method="GET" action="{{ route($statsRouteName, $statsRouteBase) }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label for="stats-season" class="mb-1 block text-sm font-medium text-gray-700">Saison</label>
            <select id="stats-season" name="saison" aria-label="Saison" onchange="this.form.submit()" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                @foreach($seasons as $s)
                    <option value="{{ $s->id }}" @selected($season?->id === $s->id)>Saison {{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <noscript><x-ui.button type="submit" variant="secondary">Anzeigen</x-ui.button></noscript>
        @if($previous)
            <p class="pb-2 text-xs text-gray-600">Kleine Zahlen: Vorsaison {{ $previous->name }}</p>
        @endif
    </form>

    @if(!$season)
        <x-ui.card><x-ui.empty-state icon="chart" title="Keine Saison angelegt" /></x-ui.card>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach($tiles as $t)
                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-sm text-gray-600">{{ $t['label'] }}</p>
                    <p class="mt-1 text-3xl font-bold {{ $t['color'] }}">{{ $now[$t['key']] }}</p>
                    @if($t['key'] === 'podiums' && $now['podiums'] > 0)
                        <p class="mt-1 text-xs text-gray-600">{{ $now['gold'] }} × Gold · {{ $now['silver'] }} × Silber · {{ $now['bronze'] }} × Bronze</p>
                    @endif
                    @if($before)
                        <p class="mt-1 text-xs text-gray-500">Vorsaison {{ $before[$t['key']] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if($now['best_wa'])
            @php $r = $now['best_wa']['result']; @endphp
            <x-ui.card title="Bestes Ergebnis nach World-Aquatics-Punkten">
                <p class="text-sm text-gray-700">
                    <span class="text-2xl font-bold text-primary">{{ $now['best_wa']['points'] }}</span> Punkte –
                    {{ $r->distance }} m {{ $r->discipline_label }} in {{ \App\Models\SwimmingTime::formatMs((int) $r->time_ms) }}
                    ({{ $r->competition?->course }}), {{ $r->competition?->name }}, {{ $r->competition?->date?->format('d.m.Y') }}
                </p>
                @if($before && $before['best_wa'])
                    <p class="mt-1 text-xs text-gray-500">Vorsaison: {{ $before['best_wa']['points'] }} Punkte</p>
                @endif
            </x-ui.card>
        @endif

        <x-ui.card title="Höhepunkte der Saison" :meta="$highlights->count() ? (string) $highlights->count() : null">
            @if($highlights->isEmpty())
                <x-ui.empty-state icon="sparkles" title="Noch keine Podestplätze, Finals oder Bestzeiten in dieser Saison"
                                  :text="$now['starts'] ? 'Die Ergebnisse stehen unter Wettkämpfe.' : 'Sobald Wettkampfergebnisse vorliegen, erscheinen sie hier.'" />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($highlights as $h)
                        <li class="flex flex-wrap items-start justify-between gap-2 py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $h->event }} <span class="font-mono font-normal text-gray-700">{{ $h->time }}</span></p>
                                <p class="text-xs text-gray-600">{{ $h->date?->format('d.m.Y') }} · {{ $h->competition }}@if($h->leadoff) · Startschwimmer Staffel @endif</p>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                @if($h->placement)
                                    <x-ui.badge :tone="$h->placement === 1 ? 'warning' : 'neutral'">Platz {{ $h->placement }}</x-ui.badge>
                                @endif
                                @if($h->final)<x-ui.badge tone="info">Finale</x-ui.badge>@endif
                                @if($h->pb)<x-ui.badge tone="brand">Bestzeit</x-ui.badge>@endif
                                @if($h->record)<x-ui.badge tone="success">Vereinsrekord</x-ui.badge>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <p class="text-xs text-gray-500">
            Gezählt werden Einzelergebnisse aus Wettkämpfen der Saison (auch die offizielle Zeit als Startschwimmer einer Staffel).
            Podestplätze sind die Plätze 1 bis 3 der jeweiligen Wertung, meist der Altersklasse.
        </p>
    @endif
</div>
@endsection
