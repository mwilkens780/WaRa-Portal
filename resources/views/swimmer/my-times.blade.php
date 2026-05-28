@extends('layouts.app')
@section('title', 'Meine Bestzeiten')
@section('page-title', 'Meine Bestzeiten')

@section('content')
<div class="mt-2 space-y-6">

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap gap-3 items-end">
        <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
            <a href="{{ route('swimmer.times', ['filter' => 'all']) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'all' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Alle
            </a>
            <a href="{{ route('swimmer.times', ['filter' => 'year', 'year' => $yearVal]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'year' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Kalenderjahr
            </a>
            <a href="{{ route('swimmer.times', ['filter' => 'season', 'season_id' => $seasonId]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors {{ $filter === 'season' ? 'bg-white text-primary shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                Saison
            </a>
        </div>

        @if($filter === 'year')
            <form method="GET" action="{{ route('swimmer.times') }}" class="flex items-center gap-2">
                <input type="hidden" name="filter" value="year">
                <select name="year" onchange="this.form.submit()"
                        class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}" @selected($y == $yearVal)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        @if($filter === 'season')
            <form method="GET" action="{{ route('swimmer.times') }}" class="flex items-center gap-2">
                <input type="hidden" name="filter" value="season">
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
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-50">
                        @foreach($discBests as $best)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-600 font-medium w-20">{{ $best->distance }} m</td>
                                <td class="px-5 py-3 w-28">
                                    <span class="font-mono font-bold text-primary">{{ $best->formatted }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-500 text-xs w-28">
                                    {{ $best->date?->format('d.m.Y') ?? '–' }}
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600">
                                    @if($best->source === 'competition')
                                        <span class="inline-block text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium mr-1">Wettkampf</span>
                                        {{ $best->label }}
                                        @if($best->location)
                                            <span class="text-gray-400">· {{ $best->location }}</span>
                                        @endif
                                    @else
                                        <span class="inline-block text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-medium mr-1">Training</span>
                                        {{ $best->label }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
</div>
@endsection
