@extends('layouts.app')
@section('title', 'Motto der Woche – ' . $trainingGroup->name)
@section('page-title', 'Motto der Woche')

@section('content')
@php $colors = $trainingGroup->colorDots; @endphp
<div class="mt-2 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <span class="w-4 h-4 rounded-full {{ $colors['dot'] }}"></span>
            <span class="font-semibold text-gray-800">{{ $trainingGroup->name }}</span>
            <span class="{{ $colors['badge'] }} text-xs font-medium px-2.5 py-1 rounded-full">
                Motto der Woche
            </span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($season)
            <form method="POST" action="{{ route('admin.training-groups.motto-generate', $trainingGroup) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-2 bg-amber-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-600 transition-colors"
                        onclick="return confirm('Wochen für die aktuelle Saison generieren? Bestehende Zuweisungen bleiben erhalten.')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Wochen generieren
                </button>
            </form>
            <form method="POST" action="{{ route('admin.training-groups.motto-reset', $trainingGroup) }}">
                @csrf
                <input type="hidden" name="force" value="0">
                <button type="submit"
                        class="flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors"
                        onclick="return confirm('Alle Wochen ohne eingetragenes Motto zurücksetzen und gleichmäßig neu verteilen?\n\nWochen mit bereits eingetragenem Motto-Text bleiben erhalten.')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Neu verteilen
                </button>
            </form>
            @endif
            <a href="{{ route('admin.training-groups.show', $trainingGroup) }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                Zurück
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    @if(!$season)
        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 text-sm">
            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <p class="text-amber-800">Keine aktive Saison gefunden. Bitte zuerst eine Saison anlegen, um Wochen generieren zu können.</p>
        </div>
    @endif

    {{-- Info-Karte --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap gap-6 text-sm">
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Saison</p>
                <p class="font-semibold text-gray-800 mt-0.5">{{ $season ? $season->name : '–' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Wochen gesamt</p>
                <p class="font-semibold text-gray-800 mt-0.5">{{ $weeks->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Mottos eingetragen</p>
                <p class="font-semibold text-green-700 mt-0.5">{{ $weeks->where('motto', '!=', null)->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Noch ausstehend</p>
                <p class="font-semibold text-amber-600 mt-0.5">{{ $weeks->filter(fn($w) => !$w->motto && $w->week_start->gte(now()->startOfWeek(\Carbon\Carbon::MONDAY)))->count() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Mitglieder</p>
                <p class="font-semibold text-gray-800 mt-0.5">{{ $allMembers->count() }}</p>
            </div>
        </div>
    </div>

    {{-- Wochen-Tabelle --}}
    @if($weeks->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm text-gray-400 font-medium">Noch keine Wochen generiert.</p>
            @if($season)
                <p class="text-xs text-gray-400 mt-1">Klicke auf „Wochen generieren", um die Saison-Wochen anzulegen.</p>
            @endif
        </div>
    @else
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Wochenübersicht</h2>
            <p class="text-xs text-gray-400 mt-0.5">Zuweisungen und Mottos anpassen. Ferienwochern werden übersprungen.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Woche</th>
                        <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Zuständig</th>
                        <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Motto</th>
                        <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($weeks as $week)
                    @php
                        $monday = now()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
                        $isCurrentWeek = $week->week_start->eq($monday);
                        $isPast        = $week->week_start->lt($monday);
                        $isUpcoming    = $week->week_start->gt($monday);

                        if ($isCurrentWeek) {
                            $statusLabel = 'Aktiv';
                            $statusCls   = 'bg-blue-100 text-blue-700';
                        } elseif ($week->motto) {
                            $statusLabel = 'Eingetragen';
                            $statusCls   = 'bg-green-100 text-green-700';
                        } elseif ($isPast) {
                            $statusLabel = 'Verpasst';
                            $statusCls   = 'bg-red-100 text-red-600';
                        } else {
                            $statusLabel = 'Ausstehend';
                            $statusCls   = 'bg-amber-100 text-amber-700';
                        }
                    @endphp
                    <tr class="{{ $isCurrentWeek ? 'bg-blue-50/40' : 'hover:bg-gray-50' }} transition-colors"
                        x-data="{ editing: false }">
                        <td class="px-5 py-3 whitespace-nowrap">
                            <p class="font-medium text-gray-800">{{ $week->week_start->format('d.m.Y') }}</p>
                            <p class="text-xs text-gray-400">KW {{ $week->week_start->weekOfYear }}</p>
                        </td>
                        <td class="px-5 py-3">
                            @if($week->user)
                                <span class="font-medium text-gray-700">{{ $week->user->firstname }} {{ $week->user->lastname }}</span>
                            @else
                                <span class="text-gray-400 italic">Niemand zugewiesen</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 max-w-xs">
                            @if($week->motto)
                                <p class="text-gray-700 text-xs leading-relaxed line-clamp-2">{{ $week->motto }}</p>
                            @elseif($week->generated_motto)
                                <p class="text-gray-400 italic text-xs leading-relaxed line-clamp-2">KI: {{ $week->generated_motto }}</p>
                            @else
                                <span class="text-gray-300 text-xs">–</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $statusCls }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button @click="editing = !editing" type="button"
                                    class="text-xs text-primary hover:underline font-medium">
                                Bearbeiten
                            </button>
                        </td>
                    </tr>
                    {{-- Inline-Edit-Formular --}}
                    <tr x-show="editing" x-transition class="{{ $isCurrentWeek ? 'bg-blue-50/40' : 'bg-gray-50' }}">
                        <td colspan="5" class="px-5 py-4">
                            <form method="POST" action="{{ route('admin.training-groups.motto-week-update', [$trainingGroup, $week]) }}"
                                  class="space-y-3">
                                @csrf @method('PUT')
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Zuständige Person</label>
                                        <select name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                            <option value="">– Niemand –</option>
                                            @foreach($allMembers as $member)
                                                <option value="{{ $member->id }}" {{ $week->user_id == $member->id ? 'selected' : '' }}>
                                                    {{ $member->firstname }} {{ $member->lastname }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">Motto-Text</label>
                                        <textarea name="motto" rows="2" maxlength="500"
                                                  placeholder="Motivierendes Motto für diese Woche..."
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none">{{ $week->motto }}</textarea>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit"
                                            class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                                        Speichern
                                    </button>
                                    <button type="button" @click="editing = false"
                                            class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-100">
                                        Abbrechen
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
