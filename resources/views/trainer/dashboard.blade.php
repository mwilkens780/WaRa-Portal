@extends('layouts.app')
@section('title', 'Trainer-Dashboard')
@section('page-title', 'Willkommen, ' . auth()->user()->name)

@section('content')
<div class="space-y-6 mt-2">

    {{-- Statistik --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Einheiten gesamt</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['sessions_total'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Diesen Monat</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['sessions_this_month'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Aktive Schwimmer</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['active_swimmers'] }}</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Letzte Einheiten --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Meine letzten Einheiten</h2>
                <a href="{{ route('trainer.sessions.index') }}" class="text-sm text-primary hover:underline">Alle</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recent_sessions as $session)
                    <a href="{{ route('trainer.sessions.show', $session) }}"
                       class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="text-center bg-primary/10 rounded-lg p-2 min-w-[50px]">
                            <p class="text-xs font-semibold text-primary">{{ $session->date->format('d.M') }}</p>
                            <p class="text-xs text-primary/70">{{ $session->date->isoFormat('ddd') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm text-gray-800 truncate">{{ $session->title }}</p>
                            <div class="flex flex-wrap gap-1 mt-0.5">
                                @foreach($session->trainingGroups as $tg)
                                    @php $tgc = \App\Models\TrainingGroup::COLORS[$tg->color] ?? \App\Models\TrainingGroup::COLORS['blue']; @endphp
                                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full {{ $tgc['badge'] }}">{{ $tg->name }}</span>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500">{{ $session->type_label }} · {{ $session->present_count }} Schwimmer</p>
                        </div>
                        <span class="text-xs text-gray-400">{{ $session->start_time }}</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-400 px-5 py-6 text-center">Noch keine Trainingseinheiten.</p>
                @endforelse
            </div>
        </div>

        {{-- Schnellzugriff + Geplante --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="font-semibold text-gray-800 mb-4">Schnellzugriff</h2>
                <a href="{{ route('trainer.sessions.create') }}"
                   class="flex items-center gap-3 bg-primary text-white px-4 py-3 rounded-lg font-medium hover:bg-primary-dark transition-colors w-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Neue Trainingseinheit anlegen
                </a>
            </div>

            @if($upcoming->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="p-5 border-b border-gray-100">
                        <h2 class="font-semibold text-gray-800">Geplante Einheiten</h2>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($upcoming as $session)
                            <a href="{{ route('trainer.sessions.show', $session) }}"
                               class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
                                <div class="w-2 h-2 bg-green-400 rounded-full flex-shrink-0"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $session->title }}</p>
                                    <p class="text-xs text-gray-500">{{ $session->date->format('d.m.Y') }} · {{ $session->start_time }} Uhr</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Neue Rekorde dieser Saison --}}
    @if($new_records->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="text-lg">🏆</span> Neue Rekorde diese Saison
            </h2>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($new_records as $r)
                <div class="flex items-center gap-3 px-5 py-3">
                    <div class="flex gap-1 shrink-0">
                        @if($r->breaks_vereinsrekord)
                            <span class="text-xs font-bold bg-primary text-white px-1.5 py-0.5 rounded">VR</span>
                        @endif
                        @if($r->breaks_landesrekord)
                            <span class="text-xs font-bold bg-amber-500 text-white px-1.5 py-0.5 rounded">LR</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800">{{ $r->user->name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $r->distance }} m {{ $r->discipline_label }}
                            @if($r->age_group) · {{ $r->age_group }} @endif
                            · {{ $r->competition->name }}
                        </p>
                    </div>
                    <span class="font-mono text-sm font-bold text-primary shrink-0">{{ $r->formatted_time }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Meine Gruppen --}}
    @if($myGroups->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Meine Gruppen</h2>
            <a href="{{ route('admin.training-groups.index') }}" class="text-sm text-primary hover:underline">Alle</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
            @foreach($myGroups as $group)
                @php $colors = $group->colorDots; @endphp
                <div class="rounded-xl border {{ $colors['border'] }} border-l-4 bg-gray-50 p-4 hover:bg-white transition-colors">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $colors['dot'] }} flex-shrink-0"></span>
                        <h3 class="font-semibold text-gray-800 text-sm truncate">{{ $group->name }}</h3>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">{{ $group->swimmers_count }} Schwimmer</p>
                    @if($group->swimmers->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mb-3">
                            @foreach($group->swimmers->take(4) as $swimmer)
                                <span class="text-xs bg-white border border-gray-200 text-gray-600 px-1.5 py-0.5 rounded">
                                    {{ $swimmer->firstname }}
                                </span>
                            @endforeach
                            @if($group->swimmers->count() > 4)
                                <span class="text-xs bg-white border border-gray-200 text-gray-400 px-1.5 py-0.5 rounded">+{{ $group->swimmers->count() - 4 }}</span>
                            @endif
                        </div>
                    @endif
                    <div class="flex gap-2 pt-2 border-t border-gray-100">
                        <a href="{{ route('admin.training-groups.show', $group) }}"
                           class="flex-1 text-center text-xs font-medium text-primary hover:underline py-1">
                            Details
                        </a>
                        <a href="{{ route('admin.training-groups.edit', $group) }}"
                           class="flex-1 text-center text-xs font-medium text-gray-500 hover:underline py-1">
                            Bearbeiten
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Diagramme --}}
    @if(count($chartLabels) > 0 || $swimmerStats->isNotEmpty())
    <div class="grid lg:grid-cols-2 gap-6">

        {{-- Team-Beteiligung letzte 10 Einheiten --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Beteiligung letzte 10 Einheiten</h2>
            @if(count($chartLabels) > 0)
                <div class="relative h-56">
                    <canvas id="chartTeam"></canvas>
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-10">Noch keine Daten vorhanden.</p>
            @endif
        </div>

        {{-- Beteiligung pro Schwimmer (letzte 90 Tage) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="font-semibold text-gray-800 mb-1">Beteiligung pro Schwimmer</h2>
            <p class="text-xs text-gray-400 mb-4">Letzte 90 Tage</p>
            @if($swimmerStats->isNotEmpty())
                <div class="relative" style="height: {{ max(120, $swimmerStats->count() * 30) }}px">
                    <canvas id="chartSwimmers"></canvas>
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-10">Noch keine Schwimmer vorhanden.</p>
            @endif
        </div>
    </div>
    @endif

</div>

@if(count($chartLabels) > 0 || $swimmerStats->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const primary = '#1B5EAB';
    const accent  = '#C0392B';

    @if(count($chartLabels) > 0)
    new Chart(document.getElementById('chartTeam'), {
        type: 'bar',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                label: 'Beteiligung %',
                data: @json($chartData),
                backgroundColor: primary + 'CC',
                borderColor: primary,
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.parsed.y + ' %'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: v => v + ' %', font: { size: 11 } },
                    grid: { color: '#f3f4f6' }
                },
                x: {
                    ticks: { font: { size: 11 } },
                    grid: { display: false }
                }
            }
        }
    });
    @endif

    @if($swimmerStats->isNotEmpty())
    new Chart(document.getElementById('chartSwimmers'), {
        type: 'bar',
        data: {
            labels: @json($swimmerStats->pluck('name')->values()),
            datasets: [{
                label: 'Beteiligung %',
                data: @json($swimmerStats->pluck('pct')->values()),
                backgroundColor: @json($swimmerStats->map(fn($s) => $s['pct'] >= 75 ? '#16a34a' : ($s['pct'] >= 50 ? '#ca8a04' : '#dc2626'))->values()),
                borderRadius: 3,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.parsed.x + ' % (' + @json($swimmerStats->pluck('attended')->values())[ctx.dataIndex] + '/' + @json($swimmerStats->pluck('total')->values())[ctx.dataIndex] + ' Einh.)'
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { callback: v => v + ' %', font: { size: 11 } },
                    grid: { color: '#f3f4f6' }
                },
                y: {
                    ticks: { font: { size: 11 } },
                    grid: { display: false }
                }
            }
        }
    });
    @endif
})();
</script>
@endif

@endsection
