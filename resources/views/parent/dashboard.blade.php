@extends('layouts.app')
@section('title', 'Eltern-Bereich')
@section('page-title', 'Eltern-Bereich')

@section('content')
<div class="mt-2 space-y-6">

    @if($children->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p class="text-gray-500">Dir sind noch keine Kinder zugewiesen.</p>
            <p class="text-sm text-gray-400 mt-1">Bitte wende dich an einen Administrator.</p>
        </div>
    @else
        @foreach($childData as $data)
            @php $child = $data['user']; @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                {{-- Kind-Header --}}
                <div class="flex items-center gap-4 p-5 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center text-lg font-bold flex-shrink-0">
                        {{ substr($child->name, 0, 1) }}
                    </div>
                    <div class="flex-1">
                        <h2 class="font-semibold text-gray-800 text-lg">{{ $child->name }}</h2>
                        @if($child->birth_date)
                            <p class="text-sm text-gray-500">{{ $child->birth_date->format('d.m.Y') }} · {{ $child->age }} Jahre</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-primary">{{ $data['trainings_this_month'] }}</p>
                        <p class="text-xs text-gray-400">Trainings diesen Monat</p>
                    </div>
                </div>

                {{-- Quick actions --}}
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50 flex flex-wrap gap-2">
                    <a href="{{ route('parent.child.trainings', $child->id) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-medium text-primary border border-primary/30 bg-primary/5 px-3 py-1.5 rounded-lg hover:bg-primary/10 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Trainings
                    </a>
                    <a href="{{ route('parent.child.signups', $child->id) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-medium {{ $data['pending_signups'] > 0 ? 'text-amber-700 border-amber-300 bg-amber-50 hover:bg-amber-100' : 'text-gray-600 border-gray-200 bg-white hover:bg-gray-50' }} border px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Wettkampf-Anmeldungen
                        @if($data['pending_signups'] > 0)
                            <span class="ml-0.5 bg-amber-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $data['pending_signups'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('parent.child.competitions', $child->id) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 border border-gray-200 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Ergebnisse
                    </a>
                    <a href="{{ route('parent.child.times', $child->id) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 border border-gray-200 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Zeiten
                    </a>
                </div>

                <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                    {{-- Persönliche Bestzeiten --}}
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">Aktuelle Bestzeiten</h3>
                            <a href="{{ route('parent.child.times', $child->id) }}"
                               class="text-xs text-primary hover:underline">Alle Zeiten</a>
                        </div>
                        @if($data['recent_bests']->isEmpty())
                            <p class="text-sm text-gray-400">Noch keine Bestzeiten.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($data['recent_bests'] as $time)
                                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                                        <span class="text-sm text-gray-700">{{ $time->distance }}m {{ $time->discipline_label }}</span>
                                        <span class="font-mono font-bold text-primary text-sm">{{ $time->formatted_time }}
                                            <span class="ml-1 text-xs bg-green-100 text-green-700 px-1 py-0.5 rounded font-sans font-medium">PB</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Letzte Wettkampfergebnisse --}}
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">Letzte Wettkampfergebnisse</h3>
                            <a href="{{ route('parent.child.competitions', $child->id) }}"
                               class="text-xs text-primary hover:underline">Alle</a>
                        </div>
                        @if($data['recent_results']->isEmpty())
                            <p class="text-sm text-gray-400">Noch keine Wettkampfergebnisse.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($data['recent_results'] as $swim)
                                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm text-gray-700 truncate">{{ $swim->competition?->name }}</p>
                                            <p class="text-xs text-gray-400">
                                                {{ $swim->distance }}m {{ $swim->discipline_label }}
                                                @if($swim->is_final)
                                                    <span class="ml-1 bg-purple-100 text-purple-700 px-1 py-0.5 rounded text-xs font-medium">Finale</span>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="text-right ml-3">
                                            <p class="font-mono font-bold text-primary text-sm">{{ $swim->formatted_time }}</p>
                                            @if($swim->best_placement)
                                                <p class="text-xs text-gray-500">Platz {{ $swim->best_placement }}</p>
                                            @endif
                                            @if($swim->is_personal_best)
                                                <span class="text-xs bg-green-100 text-green-700 px-1 py-0.5 rounded font-medium">PB</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
