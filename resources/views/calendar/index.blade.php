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
        $navCalYear = $activeSeason?->start_date->year ?? ($activeYear ?? now()->year);
        $navMonth   = $activeSeason?->start_date->month ?? 1;
        $_tmp       = \Carbon\Carbon::create($navCalYear, $navMonth, 1);
        $navIsoYear = $_tmp->isoWeekYear();
        $navIsoWeek = $_tmp->isoWeek();
    }

    $monthViewParams    = ['mode' => $mode, 'view' => 'month',    'year' => $navCalYear, 'month' => $navMonth,   'season_id' => $seasonId];
    $weekViewParams     = ['mode' => $mode, 'view' => 'week',     'year' => $navIsoYear, 'week'  => $navIsoWeek, 'season_id' => $seasonId];
    $overviewViewParams = ['mode' => $mode, 'view' => 'overview', 'year' => $navCalYear, 'season_id' => $seasonId];

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

<div class="mt-2 space-y-4">

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
                    <select name="season_id" onchange="this.form.submit()"
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
                    <select name="year" onchange="this.form.submit()"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        @foreach(range(now()->year - 3, now()->year + 2) as $y)
                            <option value="{{ $y }}" {{ ($activeYear ?? now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <div class="flex-1"></div>

            {{-- View toggle: Monat / Woche / Übersicht --}}
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

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

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
                                    <div class="text-[9px] text-green-700 font-semibold mt-0.5 leading-tight px-1 truncate" title="{{ $day['holiday'] }}">{{ $day['holiday'] }}</div>
                                @elseif($day['vacSH'] || $day['vacHH'])
                                    <div class="text-[9px] mt-0.5 leading-tight px-1 truncate
                                        {{ $day['vacSH'] ? 'text-sky-600' : 'text-violet-600' }}"
                                        title="{{ $day['vacSH'] ? 'SH: '.$day['vacSH'] : '' }}{{ ($day['vacSH'] && $day['vacHH']) ? ' / ' : '' }}{{ $day['vacHH'] ? 'HH: '.$day['vacHH'] : '' }}">
                                        @if($day['vacSH'] && $day['vacHH'])Ferien SH+HH
                                        @elseif($day['vacSH'])Ferien SH
                                        @else Ferien HH
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
                                    @php $chip = $colorMap[$evt['color']] ?? $colorMap['gray']; @endphp
                                    @if(!empty($evt['url']))
                                        <a href="{{ $evt['url'] }}"
                                           title="{{ $evt['title'] }}{{ $evt['sub'] ? ' · '.$evt['sub'] : '' }}"
                                           class="block text-[11px] rounded px-1.5 py-1 {{ $chip['chip'] }} hover:opacity-80 transition-opacity">
                                            @if($evt['time'])<div class="font-bold opacity-60 text-[10px]">{{ $evt['time'] }}</div>@endif
                                            <div class="font-medium leading-snug truncate">{{ $evt['title'] }}</div>
                                            @if($evt['sub'])<div class="opacity-60 truncate leading-tight text-[10px]">{{ $evt['sub'] }}</div>@endif
                                        </a>
                                    @else
                                        <div title="{{ $evt['title'] }}{{ $evt['sub'] ? ' · '.$evt['sub'] : '' }}"
                                             class="text-[11px] rounded px-1.5 py-1 {{ $chip['chip'] }} group/evt relative">
                                            @if($evt['time'])<div class="font-bold opacity-60 text-[10px]">{{ $evt['time'] }}</div>@endif
                                            <div class="font-medium leading-snug truncate">{{ $evt['title'] }}</div>
                                            @if($evt['sub'])<div class="opacity-60 truncate leading-tight text-[10px]">{{ $evt['sub'] }}</div>@endif
                                            @if($isTrainer && !empty($evt['id']))
                                                <a href="{{ route('calendar.events.edit', $evt['id']) }}"
                                                   class="absolute top-1 right-1 opacity-0 group-hover/evt:opacity-100 transition-opacity">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
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

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
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
                        <div class="min-h-[90px] p-1.5 border-r border-gray-50 last:border-r-0 {{ $cellBg }}
                                    {{ !$day['inMonth'] && $cellBg && $cellBg !== 'bg-gray-50/50' ? 'opacity-70' : '' }}">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs font-semibold w-6 h-6 flex items-center justify-center rounded-full
                                    {{ $day['isToday'] ? 'bg-primary text-white' : ($day['inMonth'] ? ($isWknd ? 'text-blue-600' : 'text-gray-700') : 'text-gray-300') }}">
                                    {{ $day['date']->day }}
                                </span>
                                @if($isTrainer && $day['inMonth'])
                                    <a href="{{ route('calendar.events.create', ['date' => $day['date']->format('Y-m-d')]) }}"
                                       class="text-gray-300 hover:text-primary transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    </a>
                                @endif
                            </div>
                            @if($day['holiday'])
                                <div class="text-[10px] font-semibold text-green-700 leading-tight mb-0.5 truncate" title="{{ $day['holiday'] }}">{{ $day['holiday'] }}</div>
                            @elseif($day['vacSH'] && !$day['vacHH'])
                                <div class="text-[9px] text-sky-600 leading-tight mb-0.5 truncate" title="SH: {{ $day['vacSH'] }}">Ferien SH</div>
                            @elseif($day['vacHH'] && !$day['vacSH'])
                                <div class="text-[9px] text-violet-600 leading-tight mb-0.5 truncate" title="HH: {{ $day['vacHH'] }}">Ferien HH</div>
                            @elseif($day['vacSH'] && $day['vacHH'])
                                <div class="text-[9px] text-sky-600 leading-tight mb-0.5 truncate" title="SH: {{ $day['vacSH'] }} / HH: {{ $day['vacHH'] }}">Ferien SH+HH</div>
                            @endif
                            <div class="space-y-0.5">
                                @foreach(array_slice($day['events'], 0, 3) as $evt)
                                    @php $chip = $colorMap[$evt['color']] ?? $colorMap['gray']; @endphp
                                    @if(!empty($evt['url']))
                                        <a href="{{ $evt['url'] }}"
                                           title="{{ $evt['title'] }}{{ $evt['sub'] ? ' · '.$evt['sub'] : '' }}"
                                           class="block text-[11px] leading-tight px-1.5 py-0.5 rounded {{ $chip['chip'] }} truncate hover:opacity-80 transition-opacity">
                                            @if($evt['time'])<span class="font-semibold">{{ $evt['time'] }}</span> @endif{{ $evt['title'] }}@if($evt['sub'])<span class="opacity-70"> · {{ $evt['sub'] }}</span>@endif
                                        </a>
                                    @else
                                        <div title="{{ $evt['title'] }}{{ $evt['sub'] ? ' · '.$evt['sub'] : '' }}"
                                             class="flex items-center gap-1 text-[11px] leading-tight px-1.5 py-0.5 rounded {{ $chip['chip'] }} group/evt relative">
                                            @if($evt['time'])<span class="font-semibold">{{ $evt['time'] }}</span> @endif<span class="truncate">{{ $evt['title'] }}</span>
                                            @if($isTrainer && !empty($evt['id']))
                                                <a href="{{ route('calendar.events.edit', $evt['id']) }}"
                                                   class="ml-auto opacity-0 group-hover/evt:opacity-100 flex-shrink-0 transition-opacity">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                                @if($evCount > 3)
                                    <div class="text-[10px] text-gray-400 px-1.5">+{{ $evCount - 3 }} weitere</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        @include('calendar._legend')

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

</div>
@endsection
