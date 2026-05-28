@extends('layouts.app')
@section('title', $trainingGroup->name)
@section('page-title', $trainingGroup->name)

@section('content')
@php $colors = $trainingGroup->colorDots; @endphp
<div class="mt-2 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="w-4 h-4 rounded-full {{ $colors['dot'] }}"></span>
            <span class="{{ $colors['badge'] }} text-xs font-medium px-2.5 py-1 rounded-full">
                {{ ucfirst($trainingGroup->color) }}
            </span>
            @if(!$trainingGroup->active)
                <span class="bg-gray-100 text-gray-500 text-xs font-medium px-2.5 py-1 rounded-full">Inaktiv</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.training-groups.edit', $trainingGroup) }}"
               class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Bearbeiten
            </a>
            <a href="{{ route('admin.training-groups.index') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                Zurück
            </a>
        </div>
    </div>

    @if($trainingGroup->description)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-600">{{ $trainingGroup->description }}</p>
        </div>
    @endif

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">

        {{-- Trainer --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Trainer</h2>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $trainingGroup->trainers->count() }}</span>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($trainingGroup->trainers as $trainer)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold text-sm flex-shrink-0">
                            {{ strtoupper(substr($trainer->firstname, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $trainer->firstname }} {{ $trainer->lastname }}</p>
                            <p class="text-xs text-gray-500">{{ \App\Models\User::ROLE_LABELS[$trainer->role] ?? $trainer->role }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 px-5 py-4 text-center">Keine Trainer zugewiesen.</p>
                @endforelse
            </div>
        </div>

        {{-- Schwimmer --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Schwimmer</h2>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $trainingGroup->swimmers->count() }}</span>
            </div>
            <div class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                @forelse($trainingGroup->swimmers as $swimmer)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-700 font-semibold text-sm flex-shrink-0">
                            {{ strtoupper(substr($swimmer->firstname, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $swimmer->firstname }} {{ $swimmer->lastname }}</p>
                            @if($swimmer->birth_date)
                                <p class="text-xs text-gray-500">Jg. {{ $swimmer->birth_date->format('Y') }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 px-5 py-4 text-center">Keine Schwimmer zugewiesen.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Nächste Einheiten --}}
    @if($upcomingSessions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Bevorstehende Einheiten</h2>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($upcomingSessions as $session)
                <a href="{{ route('trainer.sessions.show', $session) }}"
                   class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                    <div class="text-center bg-primary/10 rounded-lg p-2 min-w-[50px]">
                        <p class="text-xs font-semibold text-primary">{{ $session->date->format('d.M') }}</p>
                        <p class="text-xs text-primary/70">{{ $session->date->isoFormat('ddd') }}</p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-gray-800 truncate">{{ $session->title }}</p>
                        <p class="text-xs text-gray-500">{{ $session->type_label }} · {{ $session->start_time }} Uhr</p>
                    </div>
                    @if($session->trainer)
                        <span class="text-xs text-gray-400">{{ $session->trainer->firstname }} {{ $session->trainer->lastname }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Letzte Einheiten --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Zugeordnete Einheiten</h2>
            <a href="{{ route('admin.training-groups.edit', $trainingGroup) }}" class="text-xs text-primary hover:underline">Verwalten</a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($recentSessions as $session)
                <a href="{{ route('trainer.sessions.show', $session) }}"
                   class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50 transition-colors">
                    <div class="text-center bg-gray-50 rounded-lg p-2 min-w-[50px]">
                        <p class="text-xs font-semibold text-gray-700">{{ $session->date->format('d.M') }}</p>
                        <p class="text-xs text-gray-400">{{ $session->date->format('Y') }}</p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-gray-800 truncate">{{ $session->title }}</p>
                        <p class="text-xs text-gray-500">{{ $session->type_label }}</p>
                    </div>
                    @if($session->trainer)
                        <span class="text-xs text-gray-400">{{ $session->trainer->firstname }} {{ $session->trainer->lastname }}</span>
                    @endif
                </a>
            @empty
                <p class="text-sm text-gray-400 px-5 py-6 text-center">Noch keine Trainingseinheiten zugeordnet.</p>
            @endforelse
        </div>
    </div>

    {{-- Danger zone (admin only) --}}
    @if(auth()->user()->isAdmin())
    <div class="bg-white rounded-xl shadow-sm border border-red-100 p-6">
        <h2 class="font-semibold text-red-700 text-sm mb-3">Gruppe löschen</h2>
        <p class="text-sm text-gray-500 mb-4">Die Gruppe wird unwiderruflich gelöscht. Zugeordnete Trainingseinheiten bleiben erhalten, verlieren aber die Gruppenzuordnung.</p>
        <form method="POST" action="{{ route('admin.training-groups.destroy', $trainingGroup) }}"
              onsubmit="return confirm('Trainingsgruppe \"{{ $trainingGroup->name }}\" wirklich löschen?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                Gruppe löschen
            </button>
        </form>
    </div>
    @endif

</div>
@endsection
