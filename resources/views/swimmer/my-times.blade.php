@php
    // Schwimmer sieht sich selbst; Eltern sehen hier ein Kind (ParentArea\DashboardController)
    $subject  = $subject ?? auth()->user();
    $asParent = $asParent ?? false;
    [$timesRouteName, $timesRouteBase] = $timesRoute ?? ['swimmer.times', []];
    $timesUrl = fn(array $q = []) => route($timesRouteName, $timesRouteBase + $q);
    $pageTitle = $asParent ? 'Bestzeiten: ' . $subject->firstname : 'Meine Bestzeiten';
@endphp
@extends('layouts.app')
@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('content')
{{-- Rudolph-Punkte zuschaltbar, die Wahl merkt sich der Browser --}}
<div class="mt-2 space-y-6"
     x-data="{ rudolph: (() => { try { return localStorage.getItem('times.rudolph') === '1' } catch (e) { return false } })() }"
     x-init="$watch('rudolph', v => { try { localStorage.setItem('times.rudolph', v ? '1' : '0') } catch (e) {} })">

    @if($asParent)
        <a href="{{ route('parent.dashboard') }}" class="inline-block text-sm text-gray-500 hover:text-primary">← Übersicht</a>
    @endif

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap gap-3 items-end">
        {{-- Zeitraum-Filter --}}
        <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
            <a href="{{ $timesUrl(['filter' => 'all', 'course' => $courseFilter]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'all' ? 'bg-white text-primary shadow-sm' : 'text-gray-700 hover:text-gray-900' }}">
                Alle
            </a>
            <a href="{{ $timesUrl(['filter' => 'year', 'year' => $yearVal, 'course' => $courseFilter]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'year' ? 'bg-white text-primary shadow-sm' : 'text-gray-700 hover:text-gray-900' }}">
                Kalenderjahr
            </a>
            <a href="{{ $timesUrl(['filter' => 'season', 'season_id' => $seasonId, 'course' => $courseFilter]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'season' ? 'bg-white text-primary shadow-sm' : 'text-gray-700 hover:text-gray-900' }}">
                Saison
            </a>
        </div>

        {{-- Bahnlängen-Filter --}}
        <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
            <a href="{{ $timesUrl(array_merge(request()->query(), ['course' => 'all'])) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $courseFilter === 'all' ? 'bg-white text-primary shadow-sm' : 'text-gray-700 hover:text-gray-900' }}">
                Lang + Kurz
            </a>
            <a href="{{ $timesUrl(array_merge(request()->query(), ['course' => 'LB'])) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $courseFilter === 'LB' ? 'bg-white text-primary shadow-sm' : 'text-gray-700 hover:text-gray-900' }}">
                Langbahn
            </a>
            <a href="{{ $timesUrl(array_merge(request()->query(), ['course' => 'KB'])) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $courseFilter === 'KB' ? 'bg-white text-primary shadow-sm' : 'text-gray-700 hover:text-gray-900' }}">
                Kurzbahn
            </a>
        </div>

        @if($filter === 'year')
            <form method="GET" action="{{ $timesUrl() }}" class="flex items-center gap-2">
                <input type="hidden" name="filter" value="year">
                <input type="hidden" name="course" value="{{ $courseFilter }}">
                <select name="year" onchange="this.form.submit()"
                        class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}" @selected($y == $yearVal)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        @if($filter === 'season')
            <form method="GET" action="{{ $timesUrl() }}" class="flex items-center gap-2">
                <input type="hidden" name="filter" value="season">
                <input type="hidden" name="course" value="{{ $courseFilter }}">
                <select name="season_id" onchange="this.form.submit()"
                        class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @foreach($seasons as $s)
                        <option value="{{ $s->id }}" @selected($s->id == $seasonId)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        @if($filter !== 'all')
            <span class="text-sm text-gray-500">{{ $filterLabel }}</span>
        @endif

        <button type="button" @click="rudolph = !rudolph" :aria-pressed="rudolph ? 'true' : 'false'"
                :class="rudolph ? 'bg-primary text-white border-primary' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                class="sm:ml-auto px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors">
            Rudolph-Punkte
        </button>
    </div>

    <div x-show="rudolph" x-cloak>
        <x-ui.alert tone="info">
            Rudolph-Punkte (DSV-Punktetabelle {{ \App\Support\RudolphTable::EDITION }}) bewerten eine Leistung altersgerecht
            von 1 bis 20 – 20 entspricht Weltniveau der Altersklasse. Bewertet wird nur die beste
            <strong>Langbahn-Wettkampfzeit</strong>, das Alter zählt nach Jahrgang (ab 19: offene Klasse).
            Mit * markierte Strecken sind laut Tabelle in dieser Altersklasse statistisch unzureichend gesichert.
        </x-ui.alert>
    </div>

    {{-- Bests per discipline --}}
    @if($bests->isEmpty())
        <x-ui.card><x-ui.empty-state icon="clock" title="Keine Zeiten im gewählten Zeitraum" /></x-ui.card>
    @else
        @foreach($bestsByDisc as $disc => $discBests)
            {{-- Feste Spaltenbreiten: alle Lagen stehen exakt untereinander --}}
            <x-ui.table :title="$discBests->first()->discipline_label" :caption="'Bestzeiten ' . $discBests->first()->discipline_label" stack class="md:table-fixed"
                        :columns="[['label' => 'Strecke', 'class' => 'w-32'], ['label' => 'Zeit', 'align' => 'right', 'class' => 'w-28'], ['label' => 'Datum', 'class' => 'w-28'], 'Ort / Veranstaltung']">
                @foreach($discBests as $best)
                    <tr class="hover:bg-gray-50">
                        <x-ui.td label="Strecke" strong class="whitespace-nowrap">
                            {{ $best->distance }} m
                            @if($best->course_label === 'Kurzbahn')
                                <x-ui.badge tone="warning" class="ml-1">KB</x-ui.badge>
                            @elseif($best->course_label === 'Langbahn')
                                <x-ui.badge tone="info" class="ml-1">LB</x-ui.badge>
                            @endif
                        </x-ui.td>
                        <x-ui.td label="Zeit" num class="font-mono font-bold text-primary">
                            {{ $best->formatted }}
                            @if($best->rudolph ?? null)
                                <span x-show="rudolph" x-cloak class="block font-sans text-xs font-medium text-gray-700"
                                      title="Rudolph-Punkte, {{ $best->rudolph->age }}{{ isset($best->rudolph->time) ? ', Langbahn-Wettkampfzeit ' . $best->rudolph->time : '' }}">
                                    {{ $best->rudolph->points ?: 'unter 1' }} {{ $best->rudolph->points <= 1 ? 'Punkt' : 'Punkte' }}@if($best->rudolph->unreliable)*@endif
                                    @isset($best->rudolph->time)<span class="text-gray-600">({{ $best->rudolph->time }})</span>@endisset
                                </span>
                            @endif
                        </x-ui.td>
                        <x-ui.td label="Datum" muted class="text-xs tabular-nums">{{ $best->date?->format('d.m.Y') ?? '–' }}</x-ui.td>
                        {{-- Kein Quellen-Etikett mehr: Datum steht links daneben,
                             hier der Ort und die Veranstaltung. --}}
                        <x-ui.td label="Ort" class="text-xs">
                            <span class="text-gray-700">{{ $best->location ?: $best->label }}</span>
                            @if($best->location && $best->label && $best->label !== $best->location)
                                <span class="block text-xs text-gray-600 truncate" title="{{ $best->label }}">{{ $best->label }}</span>
                            @endif
                        </x-ui.td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endforeach
    @endif
</div>
@endsection
