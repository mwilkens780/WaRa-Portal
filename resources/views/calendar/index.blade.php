@extends('layouts.app')
@section('title', 'Kalender')
@section('page-title', 'Kalender')

@section('content')
@php
    $isAdmin   = auth()->user()->isAdmin();
    $isTrainer = in_array(auth()->user()->role, ['trainer', 'admin']);

    $colorMap = [
        'blue'    => ['chip' => 'bg-blue-100 text-blue-800',       'dot' => 'bg-blue-500'],
        'red'     => ['chip' => 'bg-red-100 text-red-800',         'dot' => 'bg-red-500'],
        'emerald' => ['chip' => 'bg-emerald-100 text-emerald-800', 'dot' => 'bg-emerald-500'],
        'amber'   => ['chip' => 'bg-amber-100 text-amber-800',     'dot' => 'bg-amber-500'],
        'orange'  => ['chip' => 'bg-orange-100 text-orange-800',   'dot' => 'bg-orange-500'],
        'purple'  => ['chip' => 'bg-purple-100 text-purple-800',   'dot' => 'bg-purple-500'],
        'gray'    => ['chip' => 'bg-gray-100 text-gray-700',       'dot' => 'bg-gray-400'],
    ];

    $modeLabel = $mode === 'season'
        ? ($activeSeason ? 'Saison ' . $activeSeason->name : '–')
        : 'Kalenderjahr ' . $activeYear;

    $monthNames = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

    $seasonId = $activeSeason?->id;

    // Consistent nav context regardless of active view
    if ($view === 'week') {
        $navCalYear = $weekStart->year;
        $navMonth   = $weekStart->month;
        $navIsoYear = $weekStart->isoWeekYear();
        $navIsoWeek = $weekStart->isoWeek();
    } elseif ($view === 'month') {
        $navCalYear = $year;
        $navMonth   = $month;
        $navIsoYear = $firstOfMonth->isoWeekYear();
        $navIsoWeek = $firstOfMonth->isoWeek();
    } else {
        // Uebersicht und Liste haben keinen eigenen Tag. Dann zaehlt heute -
        // sofern heute ueberhaupt in den angezeigten Zeitraum faellt. Vorher
        // wurde immer der Saisonanfang genommen: Wer aus der Uebersicht zurueck
        // in die Monats- oder Wochenansicht wechselte, landete im September.
        $heute = now();
        $imZeitraum = $mode === 'season'
            ? ($activeSeason && $heute->betweenIncluded($activeSeason->start_date, $activeSeason->end_date))
            : ($activeYear === null || (int) $activeYear === $heute->year);

        $_tmp = $imZeitraum
            ? $heute->copy()
            : \Carbon\Carbon::create(
                $activeSeason?->start_date->year ?? ($activeYear ?? $heute->year),
                $activeSeason?->start_date->month ?? 1,
                1
              );

        $navCalYear = $_tmp->year;
        $navMonth   = $_tmp->month;
        $navIsoYear = $_tmp->isoWeekYear();
        $navIsoWeek = $_tmp->isoWeek();
    }

    // Ziel des „Heute"-Knopfes: derselbe Blick, aber auf den heutigen Tag
    $heuteParams = match ($view) {
        'week'  => ['mode' => $mode, 'view' => 'week',  'year' => now()->isoWeekYear(), 'week' => now()->isoWeek()],
        'month' => ['mode' => $mode, 'view' => 'month', 'year' => now()->year, 'month' => now()->month],
        default => ['mode' => $mode, 'view' => $view,   'year' => now()->year],
    };
    // Ohne season_id: der Controller waehlt dann die Saison zum heutigen Datum
    $zeigtHeute = match ($view) {
        'week'  => isset($weekStart) && now()->betweenIncluded($weekStart, $weekEnd),
        'month' => isset($year) && isset($month) && (int) $year === now()->year && (int) $month === now()->month,
        default => false,
    };

    $monthViewParams    = ['mode' => $mode, 'view' => 'month',    'year' => $navCalYear, 'month' => $navMonth,   'season_id' => $seasonId];
    $weekViewParams     = ['mode' => $mode, 'view' => 'week',     'year' => $navIsoYear, 'week'  => $navIsoWeek, 'season_id' => $seasonId];
    $overviewViewParams = ['mode' => $mode, 'view' => 'overview', 'year' => $navCalYear, 'season_id' => $seasonId];
    $listViewParams     = ['mode' => $mode, 'view' => 'list',     'year' => $navCalYear, 'season_id' => $seasonId];

    $seasonModeParams = ['mode' => 'season', 'view' => $view, 'year' => $navCalYear, 'month' => $navMonth, 'season_id' => $seasonId];
    $yearModeParams   = ['mode' => 'year',   'view' => $view, 'year' => $navCalYear, 'month' => $navMonth];
    if ($view === 'week') {
        $seasonModeParams = array_merge($seasonModeParams, ['year' => $navIsoYear, 'week' => $navIsoWeek]);
        $yearModeParams   = array_merge($yearModeParams,   ['year' => $navIsoYear, 'week' => $navIsoWeek]);
    }

    $newEventDate = $view === 'week'
        ? $weekStart->format('Y-m-d')
        : \Carbon\Carbon::create($navCalYear, $navMonth, 1)->format('Y-m-d');
@endphp

<div class="mt-2 space-y-4"
     x-data="{
         categories: { blue: true, red: true, emerald: true, amber: true, orange: true, purple: true, gray: true, holiday: true, vacSH: true, vacHH: true },
         {{-- Detail-Sheet (calendar/_event-sheet): ein Termin oder alle eines Tages --}}
         sheetDate: '',
         sheetEvents: [],
         showEvents(date, events) {
             this.sheetDate = date;
             this.sheetEvents = events;
             this.$dispatch('open-dialog', 'calendar-events');
         },
         {{-- Als Methode statt x-init: ein try-Block ist dort kein gueltiger Ausdruck --}}
         init() {
             try { const s = localStorage.getItem('cal_filters'); if (s) Object.assign(this.categories, JSON.parse(s)); } catch (e) {}
             this.$watch('categories', v => { try { localStorage.setItem('cal_filters', JSON.stringify(v)); } catch (e) {} });
         },
     }">

    {{-- Header / Controls --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <div class="flex flex-wrap items-center gap-3">

            {{-- Mode toggle --}}
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <a href="{{ route('calendar.index', $seasonModeParams) }}"
                   class="px-3 py-1.5 font-medium transition-colors {{ $mode === 'season' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    Saison
                </a>
                <a href="{{ route('calendar.index', $yearModeParams) }}"
                   class="px-3 py-1.5 font-medium transition-colors {{ $mode === 'year' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    Kalenderjahr
                </a>
            </div>

            {{-- Season / Year selector --}}
            @if($mode === 'season')
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="mode" value="season">
                    <input type="hidden" name="view" value="{{ $view }}">
                    <select name="season_id" aria-label="Saison" onchange="this.form.submit()"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach($seasons as $s)
                            <option value="{{ $s->id }}" {{ $seasonId == $s->id ? 'selected' : '' }}>
                                Saison {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @else
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="mode" value="year">
                    <input type="hidden" name="view" value="{{ $view }}">
                    <select name="year" aria-label="Jahr" onchange="this.form.submit()"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach(range(now()->year - 3, now()->year + 2) as $y)
                            <option value="{{ $y }}" {{ ($activeYear ?? now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <div class="flex-1"></div>

            {{-- Zurück zu heute: ohne season_id, damit der Controller die Saison
                 zum heutigen Datum waehlt --}}
            <a href="{{ route('calendar.index', $heuteParams) }}"
               title="Zum heutigen Tag springen"
               class="px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors
                      {{ $zeigtHeute ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                Heute
            </a>

            {{-- View toggle: Monat / Woche / Übersicht / Liste --}}
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <a href="{{ route('calendar.index', $monthViewParams) }}"
                   class="px-3 py-1.5 font-medium transition-colors {{ $view === 'month' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    Monat
                </a>
                <a href="{{ route('calendar.index', $weekViewParams) }}"
                   class="px-3 py-1.5 font-medium transition-colors {{ $view === 'week' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    Woche
                </a>
                <a href="{{ route('calendar.index', $overviewViewParams) }}"
                   class="px-3 py-1.5 font-medium transition-colors {{ $view === 'overview' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    Übersicht
                </a>
                <a href="{{ route('calendar.index', $listViewParams) }}"
                   class="px-3 py-1.5 font-medium transition-colors {{ $view === 'list' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    Liste
                </a>
            </div>

            @if($isTrainer)
                <a href="{{ route('calendar.events.create', ['date' => $newEventDate]) }}"
                   class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Termin
                </a>
            @endif
        </div>
    </div>


    {{-- ══ WEEK VIEW ══════════════════════════════════════════════════════ --}}
    @php
        // Closure: compute cell background class from holiday/vacation data
        $cellBgFn = function(bool $isToday, ?string $holiday, ?string $vacSH, ?string $vacHH): string {
            if ($isToday) return 'bg-blue-50';
            if ($holiday) return 'bg-green-50';
            if ($vacSH)   return 'bg-sky-50';
            if ($vacHH)   return 'bg-violet-50';
            return '';
        };
    @endphp
    @if($view === 'week')

        <div class="flex items-center justify-between">
            <a href="{{ route('calendar.index', ['mode' => $mode, 'view' => 'week', 'year' => $prevWeekStart->isoWeekYear(), 'week' => $prevWeekStart->isoWeek(), 'season_id' => $seasonId]) }}"
               class="flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:bg-white rounded-lg border border-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ $prevWeekStart->format('d.m.') }}–{{ $prevWeekStart->copy()->addDays(6)->format('d.m.') }}
            </a>
            <div class="text-center">
                <h2 class="text-lg font-bold text-gray-800">
                    KW&nbsp;{{ $week }} &middot; {{ $weekStart->format('d.m.') }}&nbsp;–&nbsp;{{ $weekEnd->format('d.m.Y') }}
                </h2>
                <p class="text-sm text-gray-400">{{ $modeLabel }}</p>
            </div>
            <a href="{{ route('calendar.index', ['mode' => $mode, 'view' => 'week', 'year' => $nextWeekStart->isoWeekYear(), 'week' => $nextWeekStart->isoWeek(), 'season_id' => $seasonId]) }}"
               class="flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:bg-white rounded-lg border border-gray-200 transition-colors">
                {{ $nextWeekStart->format('d.m.') }}–{{ $nextWeekStart->copy()->addDays(6)->format('d.m.') }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <div class="min-w-[640px]">
                    {{-- Day headers --}}
                    <div class="grid grid-cols-7 border-b border-gray-100">
                        @foreach($days as $day)
                            @php $hdrBg = $cellBgFn($day['isToday'], $day['holiday'], $day['vacSH'], $day['vacHH']); @endphp
                            <div class="py-2.5 text-center border-r border-gray-100 last:border-r-0 {{ $hdrBg ?: ($day['isWeekend'] ? 'bg-gray-50/60' : '') }}">
                                <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    {{ $day['date']->isoFormat('ddd') }}
                                </div>
                                <div class="mt-1 mx-auto w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                                    {{ $day['isToday'] ? 'bg-primary text-white' : ($day['isWeekend'] ? 'text-blue-600' : 'text-gray-700') }}">
                                    {{ $day['date']->day }}
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5">{{ $day['date']->format('d.m.') }}</div>
                                @if($day['holiday'])
                                    <div x-show="categories.holiday"
                                         class="text-[10px] text-green-800 font-semibold mt-0.5 leading-tight px-1 truncate" title="{{ $day['holiday'] }}">{{ $day['holiday'] }}</div>
                                @elseif($day['vacSH'] || $day['vacHH'])
                                    <div class="text-[9px] mt-0.5 leading-tight px-1 truncate
                                        {{ $day['vacSH'] ? 'text-sky-700' : 'text-violet-600' }}"
                                        title="{{ $day['vacSH'] ? 'SH: '.$day['vacSH'] : '' }}{{ ($day['vacSH'] && $day['vacHH']) ? ' / ' : '' }}{{ $day['vacHH'] ? 'HH: '.$day['vacHH'] : '' }}">
                                        @if($day['vacSH'] && $day['vacHH'])
                                            <span x-show="categories.vacSH || categories.vacHH">Ferien SH+HH</span>
                                        @elseif($day['vacSH'])
                                            <span x-show="categories.vacSH">Ferien SH</span>
                                        @else
                                            <span x-show="categories.vacHH">Ferien HH</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    {{-- Event cells --}}
                    <div class="grid grid-cols-7 min-h-[280px]">
                        @foreach($days as $day)
                            @php $evtBg = $cellBgFn($day['isToday'], $day['holiday'], $day['vacSH'], $day['vacHH']); @endphp
                            <div class="border-r border-gray-100 last:border-r-0 p-1.5 space-y-1
                                        {{ $evtBg ?: ($day['isWeekend'] ? 'bg-gray-50/40' : '') }}">
                                @foreach($day['events'] as $evt)
                                    @include('calendar._event-chip', ['evt' => $evt, 'day' => $day['date'], 'variant' => 'week'])
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Legend --}}
        @include('calendar._legend')

    {{-- ══ MONTH VIEW ═════════════════════════════════════════════════════ --}}
    @elseif($view === 'month')

        <div class="flex items-center justify-between">
            <a href="{{ route('calendar.index', ['mode' => $mode, 'view' => 'month', 'year' => $prevMonth->year, 'month' => $prevMonth->month, 'season_id' => $seasonId]) }}"
               class="flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:bg-white rounded-lg border border-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ $monthNames[$prevMonth->month] }}
            </a>
            <h2 class="text-lg font-bold text-gray-800">
                {{ $monthNames[$month] }} {{ $year }}
                <span class="text-sm font-normal text-gray-400 ml-2">{{ $modeLabel }}</span>
            </h2>
            <a href="{{ route('calendar.index', ['mode' => $mode, 'view' => 'month', 'year' => $nextMonth->year, 'month' => $nextMonth->month, 'season_id' => $seasonId]) }}"
               class="flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:bg-white rounded-lg border border-gray-200 transition-colors">
                {{ $monthNames[$nextMonth->month] }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        {{-- Mobil: Agenda statt 7-Spalten-Raster (dort waren Termine abgeschnitten, "Tag d…") --}}
        @php
            $agendaDays = collect($weeks)->flatten(1)->filter(fn($d) => $d['inMonth'] && (count($d['events']) || $d['holiday'] || $d['vacSH'] || $d['vacHH']));
        @endphp
        <div class="sm:hidden space-y-3">
            @forelse($agendaDays as $day)
                <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <h3 class="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b border-gray-100 {{ $day['isToday'] ? 'bg-blue-50 text-primary' : 'text-gray-800' }}">
                        {{ $day['date']->isoFormat('dddd, D. MMMM') }}
                        @if($day['isToday'])<span class="text-xs bg-primary text-white px-1.5 py-0.5 rounded-full">Heute</span>@endif
                    </h3>
                    <div class="p-2 space-y-1.5">
                        @if($day['holiday'])
                            <p x-show="categories.holiday" class="px-3 text-xs font-semibold text-green-800">{{ $day['holiday'] }}</p>
                        @endif
                        @if($day['vacSH'] || $day['vacHH'])
                            <p x-show="categories.vacSH || categories.vacHH" class="px-3 text-xs text-sky-700">
                                Ferien{{ $day['vacSH'] ? ' SH: ' . $day['vacSH'] : '' }}{{ $day['vacHH'] ? ' HH: ' . $day['vacHH'] : '' }}
                            </p>
                        @endif
                        @foreach($day['events'] as $evt)
                            @include('calendar._event-chip', ['evt' => $evt, 'day' => $day['date'], 'variant' => 'agenda'])
                        @endforeach
                    </div>
                </section>
            @empty
                <x-ui.empty-state icon="calendar" title="Keine Termine in diesem Monat" />
            @endforelse
        </div>

        <div class="hidden sm:block bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="grid grid-cols-7 border-b border-gray-100">
                @foreach(['Mo','Di','Mi','Do','Fr','Sa','So'] as $dn)
                    <div class="py-2 text-center text-xs font-semibold text-gray-500">{{ $dn }}</div>
                @endforeach
            </div>
            @foreach($weeks as $wkRow)
                <div class="grid grid-cols-7 border-b border-gray-50 last:border-b-0">
                    @foreach($wkRow as $day)
                        @php
                            $isWknd  = in_array($day['date']->dayOfWeek, [6, 0]);
                            $evCount = count($day['events']);
                            $cellBg  = $cellBgFn($day['isToday'], $day['holiday'], $day['vacSH'], $day['vacHH']);
                            if (!$cellBg && !$day['inMonth']) $cellBg = 'bg-gray-50/50';
                        @endphp
                        {{-- Kein opacity mehr fuer Nachbarmonats-Tage: blendete auch Feiertags-
                             und Ferientexte unter 4,5:1 ab. Die graue Tageszahl reicht als Hinweis. --}}
                        <div class="min-h-[90px] p-1.5 border-r border-gray-50 last:border-r-0 {{ $cellBg }}">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs font-semibold w-6 h-6 flex items-center justify-center rounded-full
                                    {{ $day['isToday'] ? 'bg-primary text-white' : ($day['inMonth'] ? ($isWknd ? 'text-blue-600' : 'text-gray-700') : 'text-gray-400') }}">
                                    {{ $day['date']->day }}
                                </span>
                                @if($isTrainer && $day['inMonth'])
                                    <a href="{{ route('calendar.events.create', ['date' => $day['date']->format('Y-m-d')]) }}"
                                       aria-label="Termin am {{ $day['date']->isoFormat('D. MMMM') }} anlegen" title="Termin anlegen"
                                       class="inline-flex items-center justify-center w-7 h-7 -mr-1 rounded text-gray-400 hover:text-primary hover:bg-gray-100 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    </a>
                                @endif
                            </div>
                            @if($day['holiday'])
                                <div x-show="categories.holiday"
                                     class="text-[10px] font-semibold text-green-800 leading-tight mb-0.5 truncate" title="{{ $day['holiday'] }}">{{ $day['holiday'] }}</div>
                            @elseif($day['vacSH'] && !$day['vacHH'])
                                <div x-show="categories.vacSH"
                                     class="text-[9px] text-sky-700 leading-tight mb-0.5 truncate" title="SH: {{ $day['vacSH'] }}">Ferien SH</div>
                            @elseif($day['vacHH'] && !$day['vacSH'])
                                <div x-show="categories.vacHH"
                                     class="text-[9px] text-violet-600 leading-tight mb-0.5 truncate" title="HH: {{ $day['vacHH'] }}">Ferien HH</div>
                            @elseif($day['vacSH'] && $day['vacHH'])
                                <div x-show="categories.vacSH || categories.vacHH"
                                     class="text-[9px] text-sky-700 leading-tight mb-0.5 truncate" title="SH: {{ $day['vacSH'] }} / HH: {{ $day['vacHH'] }}">Ferien SH+HH</div>
                            @endif
                            <div class="space-y-0.5">
                                @foreach(array_slice($day['events'], 0, 3) as $evt)
                                    @include('calendar._event-chip', ['evt' => $evt, 'day' => $day['date'], 'variant' => 'month'])
                                @endforeach
                                @if($evCount > 3)
                                    <button type="button"
                                            @click="showEvents(@js($day['date']->isoFormat('dddd, D. MMMM YYYY')), @js(collect($day['events'])->map(fn($e) => \App\Support\CalendarEventPayload::make($e, $day['date'], $isTrainer))->values()))"
                                            class="w-full text-left text-xs font-medium text-gray-600 hover:text-primary px-1.5 py-0.5 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                        +{{ $evCount - 3 }} weitere
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        @include('calendar._legend')

    {{-- ══ LIST VIEW ══════════════════════════════════════════════════════ --}}
    @elseif($view === 'list')

        {{-- Print styles --}}
        <style>
            @media print {
                aside, [data-print-hide] { display: none !important; }
                [data-print-show] { display: block !important; }
                body { background: white !important; }
                .print-month-header { background: #1B5EAB !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .print-break-before { page-break-before: always; }
            }
        </style>

        {{-- Print header (screen: hidden) --}}
        <div data-print-show style="display:none" class="mb-6">
            <p class="text-lg font-bold">SG Wasserratten Norderstedt e.V. – Terminübersicht</p>
            <p class="text-sm text-gray-600">{{ $modeLabel }} · {{ $rangeStart->format('d.m.Y') }} – {{ $rangeEnd->format('d.m.Y') }}</p>
        </div>

        {{-- Controls: filter toggles + print button --}}
        <div data-print-hide class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Anzeigen:</span>

                {{-- Type toggles --}}
                <button type="button" @click="categories.blue = !categories.blue" :aria-pressed="categories.blue ? 'true' : 'false'"
                        :class="categories.blue ? 'bg-blue-100 text-blue-800 ring-2 ring-blue-300' : 'bg-gray-100 text-gray-600 line-through'"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-all">
                    <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                    Trainingseinheiten
                </button>
                <button type="button" @click="categories.red = !categories.red" :aria-pressed="categories.red ? 'true' : 'false'"
                        :class="categories.red ? 'bg-red-100 text-red-800 ring-2 ring-red-300' : 'bg-gray-100 text-gray-600 line-through'"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-all">
                    <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                    Wettkämpfe
                </button>
                <button type="button" @click="categories.emerald = !categories.emerald" :aria-pressed="categories.emerald ? 'true' : 'false'"
                        :class="categories.emerald ? 'bg-emerald-100 text-emerald-800 ring-2 ring-emerald-300' : 'bg-gray-100 text-gray-600 line-through'"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-all">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0"></span>
                    Vereinstermine
                </button>
                <button type="button"
                        :aria-pressed="(categories.amber || categories.orange || categories.purple || categories.gray) ? 'true' : 'false'"
                        @click="categories.amber = !categories.amber; categories.orange = !categories.orange; categories.purple = !categories.purple; categories.gray = !categories.gray"
                        :class="(categories.amber || categories.orange || categories.purple || categories.gray) ? 'bg-amber-100 text-amber-800 ring-2 ring-amber-300' : 'bg-gray-100 text-gray-600 line-through'"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-all">
                    <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                    Sonstige
                </button>

                <div class="flex-1"></div>

                {{-- Print button --}}
                <button type="button" onclick="window.print()"
                        class="bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 flex items-center gap-2 px-4 py-1.5 rounded-lg text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Als PDF drucken
                </button>
            </div>
        </div>

        {{-- Event list grouped by month --}}
        @if(empty($listMonths))
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center text-gray-400 text-sm">
                Keine Termine im gewählten Zeitraum.
            </div>
        @else
        <div class="space-y-6">
            @foreach($listMonths as $monthKey => $monthData)
            <div>
                {{-- Month header --}}
                <div class="print-month-header bg-[#1B5EAB] text-white px-4 py-2 rounded-t-xl flex items-center justify-between">
                    <h3 class="font-semibold text-sm tracking-wide">
                        {{ $monthNames[$monthData['month']->month] }} {{ $monthData['month']->year }}
                    </h3>
                    <span class="text-xs text-blue-200">{{ count($monthData['days']) }} {{ count($monthData['days']) === 1 ? 'Tag' : 'Tage' }} mit Terminen</span>
                </div>

                {{-- Days in this month --}}
                <div class="bg-white border border-gray-100 border-t-0 rounded-b-xl overflow-hidden divide-y divide-gray-50">
                    @foreach($monthData['days'] as $dayEntry)
                    @php
                        $date      = $dayEntry['date'];
                        $dayNames  = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
                        $dayName   = $dayNames[$date->dayOfWeek];
                        $isWeekend = in_array($date->dayOfWeek, [0, 6]);
                        $isToday   = $date->isToday();
                        $dayColors = array_values(array_unique(array_column($dayEntry['events'], 'color')));
                        $showExpr  = count($dayColors) > 0
                            ? "['" . implode("','", $dayColors) . "'].some(c => categories[c] !== false)"
                            : 'true';
                    @endphp
                    <div x-show="{{ $showExpr }}"
                         class="flex gap-0 {{ $isToday ? 'bg-blue-50/60' : ($isWeekend ? 'bg-gray-50/40' : '') }}">

                        {{-- Date column --}}
                        <div class="w-28 flex-shrink-0 px-4 py-3 border-r border-gray-100">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-xs font-bold {{ $isToday ? 'text-primary' : ($isWeekend ? 'text-blue-700' : 'text-gray-400') }}">{{ $dayName }}</span>
                                <span class="text-sm font-bold {{ $isToday ? 'text-primary' : 'text-gray-700' }}">{{ $date->format('d.') }}</span>
                            </div>
                            @if($isToday)
                                <span class="text-[10px] bg-primary text-white px-1.5 py-0.5 rounded-full font-medium">Heute</span>
                            @endif
                        </div>

                        {{-- Events column --}}
                        <div class="flex-1 px-4 py-2.5 space-y-1.5">
                            @foreach($dayEntry['events'] as $evt)
                            @php $chip = $colorMap[$evt['color']] ?? $colorMap['gray']; @endphp
                            <div x-show="categories['{{ $evt['color'] }}'] !== false"
                                 class="flex items-start gap-3">
                                {{-- Color dot --}}
                                <span class="mt-1.5 w-2 h-2 rounded-full flex-shrink-0 {{ ($colorMap[$evt['color']] ?? $colorMap['gray'])['dot'] }}"></span>
                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    @if(!empty($evt['url']))
                                        <a href="{{ $evt['url'] }}" class="font-medium text-sm text-gray-800 hover:text-primary transition-colors">
                                            {{ $evt['title'] }}
                                        </a>
                                    @else
                                        <span class="font-medium text-sm text-gray-800">{{ $evt['title'] }}</span>
                                    @endif
                                    @if($evt['sub'])
                                        <span class="text-xs text-gray-400 ml-1.5">{{ $evt['sub'] }}</span>
                                    @endif
                                    @if(isset($evt['span_start'], $evt['span_end']) && $evt['span_start'] !== $evt['span_end'])
                                        @php
                                            $spanFrom  = \Carbon\Carbon::parse($evt['span_start']);
                                            $spanTo    = \Carbon\Carbon::parse($evt['span_end']);
                                            $dateRange = $spanFrom->format('d.m.') . ' – ' . $spanTo->format('d.m.Y');
                                        @endphp
                                        <span class="text-xs text-gray-400 ml-1.5">{{ $dateRange }}</span>
                                    @endif
                                </div>
                                {{-- Time badge (only for timed events) --}}
                                @if($evt['time'])
                                    <span class="text-xs text-gray-400 flex-shrink-0 mt-0.5">{{ $evt['time'] }}</span>
                                @endif
                                {{-- Type badge --}}
                                <span class="text-[10px] px-1.5 py-0.5 rounded {{ $chip['chip'] }} flex-shrink-0">
                                    {{ match($evt['type']) {
                                        'training'    => 'Training',
                                        'competition' => 'Wettkampf',
                                        default       => $evt['sub'] ?? 'Termin',
                                    } }}
                                </span>
                            </div>
                            @endforeach
                        </div>

                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <p class="text-xs text-gray-400 text-center mt-4" data-print-hide>
            Tipp: Mit „Als PDF drucken" → Druckerziel „Als PDF speichern" exportieren.
        </p>

    {{-- ══ OVERVIEW ════════════════════════════════════════════════════════ --}}
    @else
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-base font-bold text-gray-800">{{ $modeLabel }}</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($miniMonths as $mini)
                @php $fm = $mini['month']; @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
                    <a href="{{ route('calendar.index', ['mode' => $mode, 'view' => 'month', 'year' => $fm->year, 'month' => $fm->month, 'season_id' => $seasonId]) }}"
                       class="block text-sm font-semibold text-gray-700 hover:text-primary mb-2 text-center transition-colors">
                        {{ $monthNames[$fm->month] }} {{ $fm->year }}
                    </a>
                    <div class="grid grid-cols-7 mb-1">
                        @foreach(['M','D','M','D','F','S','S'] as $wd)
                            <div class="text-center text-[9px] text-gray-400 font-semibold">{{ $wd }}</div>
                        @endforeach
                    </div>
                    @foreach($mini['weeks'] as $miniWk)
                        <div class="grid grid-cols-7">
                            @foreach($miniWk as $day)
                                @php $dotColors = $day['types'] ?? []; @endphp
                                <a href="{{ route('calendar.index', ['mode' => $mode, 'view' => 'month', 'year' => $day['date']->year, 'month' => $day['date']->month, 'season_id' => $seasonId]) }}"
                                   class="flex flex-col items-center py-0.5 rounded transition-colors {{ $day['isToday'] ? 'bg-primary/10' : 'hover:bg-gray-50' }}">
                                    <span class="text-[10px] leading-none {{ $day['inMonth'] ? ($day['isToday'] ? 'text-primary font-bold' : 'text-gray-700') : 'text-gray-300' }}">
                                        {{ $day['date']->day }}
                                    </span>
                                    @if($day['count'] > 0)
                                        <div class="flex gap-px mt-0.5">
                                            @foreach(array_slice($dotColors, 0, 3) as $dc)
                                                @php $dot = ($colorMap[$dc] ?? $colorMap['gray'])['dot']; @endphp
                                                <span class="w-1 h-1 rounded-full {{ $dot }}"></span>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="h-1.5"></div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    @include('calendar._event-sheet')
</div>
@endsection
