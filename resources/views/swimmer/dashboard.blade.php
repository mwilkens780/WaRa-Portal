@extends('layouts.app')
@section('title', 'Mein Dashboard')
@section('page-title', 'Hallo, ' . auth()->user()->name . '!')

@section('content')
<div class="space-y-6 mt-2">

    {{-- Offene Wettkampf-Anmeldeabfragen --}}
    @if($pendingSignups->isNotEmpty())
        @foreach($pendingSignups as $signup)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5"
                 x-data="{ open: true }" x-show="open">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 text-sm">Wettkampf-Anmeldung: {{ $signup->competition->name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $signup->competition->date_range }} · {{ $signup->competition->location }}
                                @if($signup->deadline) · <strong>Anmeldefrist: {{ $signup->deadline->format('d.m.Y') }}</strong>@endif
                            </p>
                            @if($signup->meeting_point || $signup->meeting_time)
                                <p class="text-xs text-gray-600 mt-1.5">
                                    <span class="font-medium">Treffpunkt:</span>
                                    @if($signup->meeting_time){{ \Illuminate\Support\Str::substr($signup->meeting_time, 0, 5) }} Uhr @endif
                                    @if($signup->meeting_point) · {{ $signup->meeting_point }} @endif
                                </p>
                            @endif
                            @if($signup->message)
                                <p class="text-sm text-gray-700 mt-2 whitespace-pre-line">{{ $signup->message }}</p>
                            @endif
                        </div>
                    </div>
                    <button @click="open = false" class="shrink-0 text-gray-300 hover:text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex gap-3 mt-4 flex-wrap">
                    <form method="POST" action="{{ route('swimmer.signup.respond', $signup) }}">
                        @csrf
                        <input type="hidden" name="status" value="attending">
                        <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                            Ich nehme teil
                        </button>
                    </form>
                    <form method="POST" action="{{ route('swimmer.signup.respond', $signup) }}"
                          x-data="{ showNote: false }">
                        @csrf
                        <input type="hidden" name="status" value="not_attending">
                        <div x-show="showNote" class="mb-2">
                            <input type="text" name="note" placeholder="Grund (optional)"
                                   class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm w-full focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="showNote = !showNote"
                                    class="px-5 py-2 border border-gray-300 text-gray-700 hover:bg-gray-100 rounded-lg text-sm transition-colors">
                                Ich kann nicht teilnehmen
                            </button>
                            <button x-show="showNote" type="submit"
                                    class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm transition-colors">
                                Absagen
                            </button>
                        </div>
                    </form>
                </div>
                @if(session('success'))
                    <p class="mt-3 text-sm text-green-700 font-medium">{{ session('success') }}</p>
                @endif
            </div>
        @endforeach
    @endif

    {{-- Bus-Buchungen für bereits zugesagte Wettkämpfe --}}
    @if($busSignups->isNotEmpty())
        @foreach($busSignups as $signup)
            @php
                $myResponse  = $signup->responses->first();
                $busBooked   = $myResponse?->bus_booked ?? false;
                $remaining   = $signup->busSeatsRemaining();
                $canBook     = !$busBooked && $remaining > 0;
            @endphp
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7h8M8 11h8M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-800 text-sm">Vereinsbus: {{ $signup->competition->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $signup->competition->date_range }}
                            @if($signup->meeting_time) · Treffpunkt {{ \Illuminate\Support\Str::substr($signup->meeting_time, 0, 5) }} Uhr @endif
                            @if($signup->meeting_point) · {{ $signup->meeting_point }} @endif
                        </p>
                        <div class="flex items-center gap-3 mt-3 flex-wrap">
                            @if($busBooked)
                                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-green-700 bg-green-100 px-3 py-1.5 rounded-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Busplatz gebucht
                                </span>
                                <form method="POST" action="{{ route('swimmer.signup.bus', $signup) }}">
                                    @csrf
                                    <button type="submit"
                                            class="text-xs text-gray-500 border border-gray-300 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                        Busplatz stornieren
                                    </button>
                                </form>
                            @elseif($canBook)
                                <form method="POST" action="{{ route('swimmer.signup.bus', $signup) }}">
                                    @csrf
                                    <button type="submit"
                                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                                        Busplatz buchen
                                    </button>
                                </form>
                                <span class="text-xs text-gray-500">Noch {{ $remaining }} von {{ $signup->bus_seats }} Plätzen frei</span>
                            @else
                                <span class="text-xs font-medium text-red-600 bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg">
                                    Alle Plätze belegt ({{ $signup->bus_seats }} / {{ $signup->bus_seats }})
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    {{-- Statistik --}}
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        <a href="{{ route('swimmer.sessions') }}"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500">Trainings gesamt</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['trainings_total'] }}</p>
        </a>
        <a href="{{ route('swimmer.sessions') }}"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500">Dieses Jahr</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['trainings_this_year'] }}</p>
        </a>
        <a href="#bestzeiten"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500">Persönliche Bestzeiten</p>
            <p class="text-3xl font-bold text-accent mt-1">{{ $stats['personal_bests'] }}</p>
        </a>
        <a href="{{ route('swimmer.competitions') }}"
           class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
            <p class="text-sm text-gray-500">Wettkämpfe</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['competitions'] }}</p>
        </a>
        <a href="{{ route('swimmer.goals.index') }}"
           class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition-shadow relative
                  {{ $goalsUnnotified > 0 ? 'border-green-300 bg-green-50/40' : 'border-gray-100' }}">
            @if($goalsUnnotified > 0)
                <span class="absolute top-3 right-3 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                </span>
            @endif
            <p class="text-sm text-gray-500">Meine Ziele</p>
            <p class="text-3xl font-bold {{ $goalsUnnotified > 0 ? 'text-green-600' : 'text-primary' }} mt-1">
                {{ $goalsAchieved }}<span class="text-lg font-normal text-gray-400">/{{ $goalsTotal }}</span>
            </p>
            @if($goalsTotal > 0)
                <div class="bg-gray-100 rounded-full h-1.5 mt-2">
                    <div class="{{ $goalsUnnotified > 0 ? 'bg-green-500' : 'bg-primary' }} h-1.5 rounded-full"
                         style="width: {{ $goalsTotal > 0 ? round($goalsAchieved / $goalsTotal * 100) : 0 }}%"></div>
                </div>
            @else
                <p class="text-xs text-gray-400 mt-2">Noch keine Ziele</p>
            @endif
        </a>

        {{-- km diese Woche --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 col-span-2 lg:col-span-1">
            <p class="text-sm text-gray-500">km diese Woche</p>
            <p class="text-3xl font-bold text-teal-600 mt-1">
                @if($stats['km_this_week'] > 0)
                    {{ number_format($stats['km_this_week'], $stats['km_this_week'] < 10 ? 2 : 1, ',', '') }}
                @else
                    –
                @endif
            </p>
            <p class="text-xs text-gray-400 mt-1">aus Trainingsplänen · {{ $stats['attended_week'] }} Einh.</p>
        </div>
    </div>

    {{-- Trainingsbeteiligung --}}
    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500 mb-1">Beteiligung in dieser Saison</p>
            <div class="flex items-end gap-3">
                <p class="text-3xl font-bold text-primary">{{ $stats['participation_season'] }} %</p>
                <p class="text-xs text-gray-400 pb-1">{{ $stats['attended_season'] }} / {{ $stats['sessions_season'] }} Trainings</p>
            </div>
            <div class="bg-gray-100 rounded-full h-2.5 mt-3">
                <div class="bg-primary h-2.5 rounded-full" style="width: {{ min(100, $stats['participation_season']) }}%"></div>
            </div>
            @if($stats['season_label'])
                <p class="text-xs text-gray-400 mt-1">Saison {{ $stats['season_label'] }}</p>
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500 mb-1">Beteiligung in dieser Woche</p>
            <div class="flex items-end gap-3">
                <p class="text-3xl font-bold text-primary">{{ $stats['participation_week'] }} %</p>
                <p class="text-xs text-gray-400 pb-1">{{ $stats['attended_week'] }} / {{ $stats['sessions_week'] }} Trainings</p>
            </div>
            <div class="bg-gray-100 rounded-full h-2.5 mt-3">
                <div class="bg-primary h-2.5 rounded-full" style="width: {{ min(100, $stats['participation_week']) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Nächster Wettkampf --}}
    @if($next_competition)
    <a href="{{ route('swimmer.competitions') }}"
       class="block bg-white rounded-xl shadow-sm border border-primary/20 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-center gap-4">
            <div class="bg-primary/10 rounded-xl p-3 flex-shrink-0 text-center min-w-[64px]">
                <p class="text-2xl font-bold text-primary leading-none">{{ $next_competition->date->format('d') }}</p>
                <p class="text-xs font-semibold text-primary/70 mt-0.5">{{ $next_competition->date->isoFormat('MMM') }}</p>
                <p class="text-xs text-primary/50">{{ $next_competition->date->year }}</p>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-0.5">Nächster Wettkampf</p>
                <p class="font-semibold text-gray-800 truncate">{{ $next_competition->name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $next_competition->location }}
                    @if($next_competition->type_label) · {{ $next_competition->type_label }} @endif
                    @if($next_competition->course) · {{ $next_competition->course_label }} @endif
                </p>
            </div>
            <div class="flex-shrink-0">
                @php $daysUntil = today()->diffInDays($next_competition->date, false); @endphp
                @if($daysUntil === 0)
                    <span class="text-xs font-bold bg-red-100 text-red-700 px-2 py-1 rounded-full">Heute</span>
                @elseif($daysUntil <= 7)
                    <span class="text-xs font-bold bg-amber-100 text-amber-700 px-2 py-1 rounded-full">in {{ $daysUntil }} Tagen</span>
                @else
                    <span class="text-xs text-gray-400">in {{ $daysUntil }} Tagen</span>
                @endif
            </div>
        </div>
    </a>
    @endif

    {{-- Geplante Trainings nächste 2 Wochen --}}
    @if($upcoming_sessions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Trainings – nächste 2 Wochen</h2>
            <span class="text-xs text-gray-400">{{ $upcoming_sessions->count() }} Einheit(en)</span>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($upcoming_sessions as $session)
                @php
                    $isAbsent = $my_pre_absences->has($session->id);
                    $regOpen  = $session->registration_open;
                @endphp
                <div class="flex items-center gap-4 px-5 py-3 {{ $isAbsent ? 'bg-red-50/50' : ($regOpen ? 'bg-green-50/30' : 'hover:bg-gray-50') }} transition-colors">
                    <a href="{{ route('swimmer.session.show', $session) }}" class="flex items-center gap-4 flex-1 min-w-0">
                        <div class="text-center {{ $isAbsent ? 'bg-red-100' : ($regOpen ? 'bg-green-100' : 'bg-primary/10') }} rounded-lg p-2 min-w-[54px]">
                            <p class="text-xs font-bold {{ $isAbsent ? 'text-red-600' : ($regOpen ? 'text-green-700' : 'text-primary') }}">{{ $session->date->format('d.M') }}</p>
                            <p class="text-xs {{ $isAbsent ? 'text-red-400' : ($regOpen ? 'text-green-500' : 'text-primary/60') }}">{{ $session->date->isoFormat('ddd') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $session->title }}</p>
                                @if($isAbsent)
                                    <span class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">Abgesagt</span>
                                @endif
                                @if($regOpen)
                                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">Anmeldung offen</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ $session->start_time }} Uhr
                                @if($session->location) · {{ $session->location }} @endif
                                · <span class="{{ $session->type_color }} text-xs px-1.5 py-0.5 rounded-full">{{ $session->type_label }}</span>
                            </p>
                            @if($isAbsent && $my_pre_absences[$session->id])
                                <p class="text-xs text-red-500 mt-0.5 italic">{{ $my_pre_absences[$session->id] }}</p>
                            @endif
                        </div>
                    </a>
                    {{-- Absage/Rücknahme Button --}}
                    <form method="POST" action="{{ route('swimmer.session.cancel', $session) }}" class="flex-shrink-0"
                          x-data="{ open: false }">
                        @csrf
                        @if($isAbsent)
                            <button type="submit"
                                    class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                Absage zurücknehmen
                            </button>
                        @else
                            <div x-show="!open" @click.prevent="open = true">
                                <button type="button"
                                        class="text-xs text-red-600 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors">
                                    Absagen
                                </button>
                            </div>
                            <div x-show="open" x-cloak class="flex items-center gap-2">
                                <input type="text" name="note" placeholder="Grund (optional)"
                                       class="text-xs border border-gray-200 rounded px-2 py-1 w-36 focus:outline-none focus:ring-1 focus:ring-primary">
                                <button type="submit"
                                        class="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 transition-colors">
                                    Bestätigen
                                </button>
                                <button type="button" @click="open = false"
                                        class="text-xs text-gray-400 hover:text-gray-600">
                                    ✕
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Bestzeiten mit Tabs --}}
        <div id="bestzeiten" class="bg-white rounded-xl shadow-sm border border-gray-100" x-data="{ bestTab: 'alltime' }">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Meine Bestzeiten</h2>
                <a href="{{ route('swimmer.times') }}" class="text-sm text-primary hover:underline">Alle Bestzeiten</a>
            </div>
            <div class="flex border-b border-gray-100 text-xs">
                <button @click="bestTab = 'alltime'"
                        :class="bestTab === 'alltime' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2.5 font-medium border-b-2 -mb-px transition-colors">
                    Allzeit
                </button>
                <button @click="bestTab = 'year'"
                        :class="bestTab === 'year' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2.5 font-medium border-b-2 -mb-px transition-colors">
                    {{ now()->year }}
                </button>
                <button @click="bestTab = 'season'"
                        :class="bestTab === 'season' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="px-4 py-2.5 font-medium border-b-2 -mb-px transition-colors">
                    Saison
                </button>
            </div>

            @php
                $bestsTable = function($bests, $emptyText) {
                    return $bests;
                };
            @endphp

            {{-- Allzeit --}}
            <div x-show="bestTab === 'alltime'">
                @if($allBests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Noch keine Zeiten erfasst.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Bestzeit</th>
                                    <th class="px-5 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($allBests as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $row->label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">{{ $row->formatted }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            @if($row->source === 'competition')
                                                <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Wettkampf</span>
                                            @elseif($row->source === 'training')
                                                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Training</span>
                                            @else
                                                <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">PB</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Jahres-Bestzeiten --}}
            <div x-show="bestTab === 'year'" x-cloak>
                @if($yearBests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Keine Zeiten in {{ now()->year }}.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Bestzeit {{ now()->year }}</th>
                                    <th class="px-5 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($yearBests as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $row->label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">{{ $row->formatted }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            @if($row->source === 'competition')
                                                <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Wettkampf</span>
                                            @elseif($row->source === 'training')
                                                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Training</span>
                                            @else
                                                <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">PB</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Saison-Bestzeiten --}}
            <div x-show="bestTab === 'season'" x-cloak>
                @if($seasonBests->isEmpty())
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Keine Zeiten in der aktuellen Saison.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-5 py-2 font-semibold text-gray-500 text-xs">Disziplin / Distanz</th>
                                    <th class="text-right px-5 py-2 font-semibold text-gray-500 text-xs">Saisonbest</th>
                                    <th class="px-5 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($seasonBests as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2.5 text-gray-700">{{ $row->label }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-bold text-primary">{{ $row->formatted }}</td>
                                        <td class="px-5 py-2.5 text-right">
                                            @if($row->source === 'competition')
                                                <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Wettkampf</span>
                                            @elseif($row->source === 'training')
                                                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">Training</span>
                                            @else
                                                <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">PB</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Letzte Trainings & Wettkämpfe --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-800">Letzte Trainings</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recent_sessions as $session)
                        @php $diary = $session->diaries->first(); @endphp
                        <a href="{{ route('swimmer.session.show', $session) }}"
                           class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                            <div class="text-center bg-primary/10 rounded-lg p-2 min-w-[50px]">
                                <p class="text-xs font-semibold text-primary">{{ $session->date->format('d.M') }}</p>
                                <p class="text-xs text-primary/60">{{ $session->date->isoFormat('ddd') }}</p>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $session->title }}</p>
                                <p class="text-xs text-gray-500">{{ $session->trainer?->name ?? '–' }} · {{ $session->type_label }}</p>
                            </div>
                            {{-- Tagebuch-Badge --}}
                            @if($diary)
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    @if($diary->mood)
                                        <span class="text-base leading-none">{{ $diary->mood_emoji }}</span>
                                    @endif
                                    @if($diary->perceived_intensity)
                                        <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium">
                                            {{ $diary->perceived_intensity }}/10
                                        </span>
                                    @endif
                                    @if(!$diary->mood && !$diary->perceived_intensity)
                                        <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">
                                            <svg class="w-3 h-3 inline -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Tagebuch
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </a>
                    @empty
                        <p class="text-sm text-gray-400 px-5 py-4 text-center">Noch keine Trainings.</p>
                    @endforelse
                </div>
            </div>

            @if($recent_results->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100">
                        <h2 class="font-semibold text-gray-800">Letzte Wettkampfergebnisse</h2>
                        <a href="{{ route('swimmer.competitions') }}" class="text-sm text-primary hover:underline">Alle</a>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($recent_results as $swim)
                            <a href="{{ route('swimmer.competitions') }}"
                               class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">{{ $swim->competition?->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $swim->distance }}m {{ $swim->discipline_label }}
                                        @if($swim->is_final)
                                            <span class="ml-1 bg-purple-100 text-purple-700 px-1 py-0.5 rounded text-xs font-medium">Finale</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-mono font-bold text-primary text-sm">{{ $swim->formatted_time }}</p>
                                    @if($swim->best_placement)
                                        <p class="text-xs text-gray-500">Platz {{ $swim->best_placement }}</p>
                                    @endif
                                </div>
                                @if($swim->is_personal_best)
                                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium flex-shrink-0">PB</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
