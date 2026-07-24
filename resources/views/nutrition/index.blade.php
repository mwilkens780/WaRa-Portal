@extends('layouts.app')
@section('title', 'Ernährungsberatung')
@section('page-title', 'Ernährungsberatung')

@section('content')
<div class="mt-2 space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <div>
                <h2 class="text-base font-semibold text-gray-800">Kandidaten – Ernährungsberatung</h2>
                <p class="text-xs text-gray-400 mt-0.5">Schwimmer, die der Erfassung von Ernährungsdaten zugestimmt haben</p>
            </div>
        </div>

        @if($candidates->isEmpty())
        <div class="px-6 py-8 text-center text-sm text-gray-400">Keine Kandidaten gefunden.</div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($candidates as $candidate)
            <a href="{{ route('nutrition.show', $candidate) }}"
               class="flex items-center justify-between px-6 py-3.5 hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-xs font-bold text-green-700 flex-shrink-0">
                        {{ strtoupper(substr($candidate->firstname ?: $candidate->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $candidate->name }}</p>
                        <p class="text-xs text-gray-400">{{ $candidate->birth_date ? $candidate->birth_date->format('d.m.Y') : '' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400">
                        {{ $candidate->healthDocuments()->where('category', 'nutrition')->count() }} Dokument(e)
                    </span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
