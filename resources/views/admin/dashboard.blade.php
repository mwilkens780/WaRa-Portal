@extends('layouts.app')
@section('title', 'Admin-Dashboard')
@section('page-title', 'Admin-Dashboard')

@section('content')
<div class="space-y-6 mt-2">

    {{-- Statistik-Kacheln --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Benutzer gesamt</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['total_users'] }}</p>
            @if($stats['inactive_users'] > 0)
                <p class="text-xs text-amber-600 mt-1">{{ $stats['inactive_users'] }} inaktiv</p>
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Schwimmer</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['swimmers'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['parents'] }} Elternteile</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Training diesen Monat</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['sessions_this_month'] }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $stats['trainers'] }} Trainer</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Wettkämpfe</p>
            <p class="text-3xl font-bold text-primary mt-1">{{ $stats['competitions_total'] }}</p>
            <p class="text-xs text-green-600 mt-1">{{ $stats['upcoming_competitions'] }} bevorstehend</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Letzte Trainingseinheiten --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Letzte Trainingseinheiten</h2>
                <a href="{{ route('trainer.sessions.index') }}"
                   class="text-sm text-primary hover:underline">Alle anzeigen</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recent_sessions as $session)
                    <div class="flex items-center gap-4 px-5 py-3">
                        <div class="text-center bg-primary/10 rounded-lg p-2 min-w-[50px]">
                            <p class="text-xs font-medium text-primary">{{ $session->date->format('d.M') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm text-gray-800 truncate">{{ $session->title }}</p>
                            <p class="text-xs text-gray-500">{{ $session->trainer->name }} · {{ $session->type_label }}</p>
                        </div>
                        <span class="text-xs text-gray-400">{{ $session->start_time }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 px-5 py-4">Noch keine Trainingseinheiten.</p>
                @endforelse
            </div>
        </div>

        {{-- Bevorstehende Wettkämpfe --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Bevorstehende Wettkämpfe</h2>
                <a href="{{ route('admin.competitions.index') }}"
                   class="text-sm text-primary hover:underline">Alle anzeigen</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($upcoming_competitions as $comp)
                    <div class="flex items-center gap-4 px-5 py-3">
                        <div class="text-center bg-accent/10 rounded-lg p-2 min-w-[50px]">
                            <p class="text-xs font-medium text-accent">{{ $comp->date->format('d.M') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm text-gray-800 truncate">{{ $comp->name }}</p>
                            <p class="text-xs text-gray-500">{{ $comp->location }}</p>
                        </div>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $comp->type_label }}</span>
                    </div>
                @empty
                    <div class="px-5 py-4 text-sm text-gray-400">
                        Keine bevorstehenden Wettkämpfe.
                        <a href="{{ route('admin.competitions.create') }}" class="text-primary hover:underline">Jetzt anlegen</a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Neue Rekorde dieser Saison --}}
    @if($new_records->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                <span class="text-lg">🏆</span> Neue Rekorde diese Saison
            </h2>
            <a href="{{ route('admin.records.index') }}" class="text-sm text-primary hover:underline">Alle Rekorde</a>
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

    {{-- Schnellzugriff --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="font-semibold text-gray-800 mb-4">Schnellzugriff</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.users.create') }}"
               class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Neuer Benutzer
            </a>
            <a href="{{ route('admin.competitions.create') }}"
               class="flex items-center gap-2 bg-accent text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-accent-dark transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Neuer Wettkampf
            </a>
            <a href="{{ route('trainer.sessions.create') }}"
               class="flex items-center gap-2 bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Neue Trainingseinheit
            </a>
        </div>
    </div>
</div>
@endsection
