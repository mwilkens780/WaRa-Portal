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
<div class="mt-2 space-y-6">

    @if($asParent)
        <a href="{{ route('parent.dashboard') }}" class="inline-block text-sm text-gray-500 hover:text-primary">← Übersicht</a>
    @endif

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap gap-3 items-end">
        {{-- Zeitraum-Filter --}}
        <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
            <a href="{{ $timesUrl(['filter' => 'all', 'course' => $courseFilter]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'all' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Alle
            </a>
            <a href="{{ $timesUrl(['filter' => 'year', 'year' => $yearVal, 'course' => $courseFilter]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'year' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Kalenderjahr
            </a>
            <a href="{{ $timesUrl(['filter' => 'season', 'season_id' => $seasonId, 'course' => $courseFilter]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'season' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Saison
            </a>
        </div>

        {{-- Bahnlängen-Filter --}}
        <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
            <a href="{{ $timesUrl(array_merge(request()->query(), ['course' => 'all'])) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $courseFilter === 'all' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Lang + Kurz
            </a>
            <a href="{{ $timesUrl(array_merge(request()->query(), ['course' => 'LB'])) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $courseFilter === 'LB' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Langbahn
            </a>
            <a href="{{ $timesUrl(array_merge(request()->query(), ['course' => 'KB'])) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $courseFilter === 'KB' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
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
    </div>

    {{-- Bests per discipline --}}
    @if($bests->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Keine Zeiten im gewählten Zeitraum.
        </div>
    @else
        @foreach($bestsByDisc as $disc => $discBests)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-700 text-sm">{{ $discBests->first()->discipline_label }}</h2>
                </div>
                {{-- Feste Spaltenbreiten: alle Lagen stehen exakt untereinander --}}
                <div class="overflow-x-auto">
                <table class="w-full text-sm table-fixed min-w-[560px]">
                    <colgroup>
                        <col class="w-32">   {{-- Strecke + Bahn --}}
                        <col class="w-28">   {{-- Zeit --}}
                        <col class="w-28">   {{-- Datum --}}
                        <col>                {{-- Quelle: Rest --}}
                    </colgroup>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($discBests as $best)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-600 font-medium whitespace-nowrap">
                                    {{ $best->distance }} m
                                    @if($best->course_label === 'Kurzbahn')
                                        <span class="ml-1 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-medium">KB</span>
                                    @elseif($best->course_label === 'Langbahn')
                                        <span class="ml-1 text-xs bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded-full font-medium">LB</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums px-5 py-3">
                                    <span class="font-mono font-bold text-primary">{{ $best->formatted }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-500 text-xs">
                                    {{ $best->date?->format('d.m.Y') ?? '–' }}
                                </td>
                                {{-- Kein Quellen-Etikett mehr: Datum steht links daneben,
                                     hier der Ort und die Veranstaltung. --}}
                                <td class="px-5 py-3 text-xs text-gray-600">
                                    <span class="text-gray-700">{{ $best->location ?: $best->label }}</span>
                                    @if($best->location && $best->label && $best->label !== $best->location)
                                        <span class="block text-[11px] text-gray-400 truncate" title="{{ $best->label }}">{{ $best->label }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
