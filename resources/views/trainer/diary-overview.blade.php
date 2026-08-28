@extends('layouts.app')
@section('title', 'Einschätzungen Übersicht')
@section('page-title', 'Einschätzungen Übersicht')

@section('content')
<div class="mt-2 space-y-5">

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" action="{{ route('trainer.diary.overview') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">Sportler</label>
                <select name="user_id" onchange="this.form.submit()"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                    <option value="">– Sportler wählen –</option>
                    @foreach($swimmers as $s)
                        <option value="{{ $s->id }}" @selected($s->id == $userId)>
                            {{ $s->lastname }}, {{ $s->firstname }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if($userId)
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                        <input type="hidden" name="user_id" value="{{ $userId }}">
                        <input type="checkbox" name="only_diff" value="1" @checked($onlyDiff) onchange="this.form.submit()"
                               class="rounded text-primary">
                        Nur starke Abweichungen
                    </label>
                </div>
            @endif
        </form>
    </div>

    @if(!$userId)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-16 text-center text-gray-400">
            <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Sportler auswählen um die Einschätzungen zu sehen.
        </div>
    @elseif($entries->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Keine Einschätzungen gefunden.
        </div>
    @else
        {{-- Statistik-Zusammenfassung --}}
        @php
            $withBoth    = $entries->filter(fn($e) => $e->self_score !== null && $e->trainer_score !== null);
            $matches     = $withBoth->filter(fn($e) => $e->deviation_level === 'match')->count();
            $minor       = $withBoth->filter(fn($e) => $e->deviation_level === 'minor')->count();
            $major       = $withBoth->filter(fn($e) => $e->deviation_level === 'major')->count();
            $avgSelf     = $entries->whereNotNull('self_score')->avg('self_score');
            $avgTrainer  = $withBoth->avg('trainer_score');
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-center">
                <p class="text-xs text-gray-500">Ø Selbst</p>
                <p class="text-2xl font-bold text-primary">{{ $avgSelf !== null ? number_format($avgSelf, 1) : '–' }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-center">
                <p class="text-xs text-gray-500">Ø Trainer</p>
                <p class="text-2xl font-bold text-primary">{{ $avgTrainer !== null ? number_format($avgTrainer, 1) : '–' }}</p>
            </div>
            <div class="bg-white rounded-xl border border-green-100 shadow-sm px-4 py-3 text-center bg-green-50">
                <p class="text-xs text-green-600">Übereinstimmung</p>
                <p class="text-2xl font-bold text-green-700">{{ $matches }}</p>
            </div>
            <div class="bg-white rounded-xl border border-red-100 shadow-sm px-4 py-3 text-center bg-red-50">
                <p class="text-xs text-red-600">Starke Abweichung</p>
                <p class="text-2xl font-bold text-red-700">{{ $major }}</p>
            </div>
        </div>

        {{-- Einträge --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left font-medium">Datum</th>
                        <th class="px-4 py-3 text-left font-medium">Einheit</th>
                        <th class="px-4 py-3 text-center font-medium">Selbst</th>
                        <th class="px-4 py-3 text-center font-medium">Trainer</th>
                        <th class="px-4 py-3 text-left font-medium">Abweichung</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($entries as $entry)
                        @php $dev = $entry->deviation_level; @endphp
                        <tr class="hover:bg-gray-50 {{ $dev === 'major' ? 'bg-red-50/30' : '' }}">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ $entry->session?->date?->format('d.m.Y') ?? '–' }}
                            </td>
                            <td class="px-4 py-3 text-gray-800 font-medium">
                                {{ $entry->session?->title ?? '–' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($entry->self_score !== null)
                                    <span class="text-base font-bold {{ \App\Models\TrainingDiary::scoreColor($entry->self_score) }}">
                                        {{ $entry->self_score }}
                                    </span>
                                @else
                                    <span class="text-gray-300">–</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center" x-data="{ editing: false }">
                                @if($entry->trainer_score !== null)
                                    <template x-if="!editing">
                                        <button @click="editing = true"
                                                class="text-base font-bold {{ \App\Models\TrainingDiary::scoreColor($entry->trainer_score) }} hover:opacity-70">
                                            {{ $entry->trainer_score }}
                                        </button>
                                    </template>
                                @else
                                    <template x-if="!editing">
                                        <button @click="editing = true"
                                                class="text-gray-300 hover:text-primary text-sm font-medium">+ Vergeben</button>
                                    </template>
                                @endif
                                <template x-if="editing">
                                    <form method="POST"
                                          action="{{ route('trainer.sessions.trainer-score', [$entry->training_session_id, $entry->user_id]) }}"
                                          class="inline-flex items-center gap-1" @submit="editing = false">
                                        @csrf
                                        <input type="number" name="trainer_score"
                                               value="{{ $entry->trainer_score ?? $entry->self_score ?? 5 }}"
                                               min="0" max="10" required
                                               class="w-14 text-center border border-primary rounded-lg px-1 py-0.5 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-primary/30"
                                               x-init="$nextTick(() => $el.focus())">
                                        <button type="submit" class="text-green-600 hover:text-green-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                        <button type="button" @click="editing = false" class="text-gray-400 hover:text-gray-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </form>
                                </template>
                            </td>
                            <td class="px-4 py-3">
                                @if($dev === 'match')
                                    <span class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        Übereinstimmung
                                    </span>
                                @elseif($dev === 'minor')
                                    <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">
                                        ~ {{ $entry->deviation }} Pkt.
                                    </span>
                                @elseif($dev === 'major')
                                    <span class="inline-flex items-center gap-1 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/></svg>
                                        {{ $entry->deviation }} Pkt. Abw.
                                    </span>
                                @else
                                    <span class="text-xs text-gray-300">Noch kein Score</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($entry->training_session_id)
                                    <a href="{{ route('trainer.sessions.show', $entry->training_session_id) }}"
                                       class="text-xs text-gray-400 hover:text-primary transition-colors">Einheit →</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
