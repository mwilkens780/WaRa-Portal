@extends('layouts.app')
@section('title', 'Motto der Woche')
@section('page-title', 'Motto der Woche')

@section('content')
<div class="mt-2 space-y-6">

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

    @if($groupIds->isEmpty())
        {{-- No motto-enabled groups --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <svg class="w-16 h-16 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            <p class="text-gray-500 font-medium">Kein Motto der Woche verfügbar.</p>
            <p class="text-sm text-gray-400 mt-1">Das Feature ist für deine Gruppe(n) noch nicht aktiviert.</p>
        </div>
    @else

    {{-- ── Aktuelles Motto ───────────────────────────────────────────────────── --}}
    @if($currentMottos->isNotEmpty())
    <div class="space-y-4">
        @foreach($currentMottos as $currentMotto)
        @php $groupColor = \App\Models\TrainingGroup::COLORS[$currentMotto->group->color ?? 'blue'] ?? \App\Models\TrainingGroup::COLORS['blue']; @endphp
        <div class="bg-gradient-to-br from-primary to-primary-dark text-white rounded-2xl p-6 shadow-md relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -translate-y-8 translate-x-8"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full translate-y-6 -translate-x-6"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-yellow-300" fill="currentColor" viewBox="0 0 24 24"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    <span class="text-blue-200 text-xs font-semibold uppercase tracking-widest">Motto der Woche</span>
                    <span class="text-xs text-blue-300 ml-1">· {{ $currentMotto->group->name }}</span>
                </div>
                @if($currentMotto->motto)
                    <blockquote class="text-xl font-semibold leading-snug">"{{ $currentMotto->motto }}"</blockquote>
                    @if($currentMotto->user)
                        <p class="mt-3 text-blue-200 text-sm">
                            — {{ $currentMotto->user->firstname }} {{ $currentMotto->user->lastname }}
                        </p>
                    @endif
                @else
                    <p class="text-xl font-semibold text-blue-200 italic">Noch kein Motto für diese Woche eingetragen.</p>
                    @if($currentMotto->user)
                        <p class="mt-2 text-blue-300 text-sm">
                            Zuständig: {{ $currentMotto->user->firstname }} {{ $currentMotto->user->lastname }}
                        </p>
                    @endif
                @endif
                <p class="mt-3 text-blue-300 text-xs">KW {{ $monday->weekOfYear }} · {{ $monday->format('d.m.Y') }}</p>
            </div>
        </div>
        @endforeach
    </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
            <p class="text-gray-400 text-sm">Für die aktuelle Woche ist noch kein Motto eingetragen.</p>
        </div>
    @endif

    {{-- ── Meine zugewiesenen Wochen ──────────────────────────────────────────── --}}
    @if($myWeeks->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Meine Wochen</h2>
            <p class="text-xs text-gray-400 mt-0.5">Wochen, in denen du für das Motto zuständig bist</p>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($myWeeks as $week)
            @php
                $monday   = now()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
                $isCurrent = $week->week_start->eq($monday);
                $isPast    = $week->week_start->lt($monday);
                $days      = $week->daysUntilStart();
                $groupColor = \App\Models\TrainingGroup::COLORS[$week->group->color ?? 'blue'] ?? \App\Models\TrainingGroup::COLORS['blue'];
            @endphp
            <div class="p-5" x-data="{ editing: false, aiLoading: false, generatedText: '' }">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="font-semibold text-gray-800">KW {{ $week->week_start->weekOfYear }}</span>
                            <span class="text-xs text-gray-500">{{ $week->week_start->format('d.m.Y') }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $groupColor['badge'] }}">{{ $week->group->name }}</span>
                            @if($isCurrent)
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Diese Woche</span>
                            @elseif($isPast)
                                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Vergangen</span>
                            @elseif($days === 7)
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">Nächste Woche</span>
                            @else
                                <span class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full">In {{ $days }} Tagen</span>
                            @endif
                        </div>
                        @if($week->motto)
                            <div class="mt-2 bg-green-50 border border-green-100 rounded-lg px-4 py-3">
                                <p class="text-sm text-green-800 font-medium leading-relaxed">"{{ $week->motto }}"</p>
                                <p class="text-xs text-green-600 mt-1">Eingetragen</p>
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic mt-1">Noch kein Motto eingetragen.</p>
                        @endif
                    </div>
                    @if(!$isPast || $isCurrent)
                    <button @click="editing = !editing" type="button"
                            class="shrink-0 flex items-center gap-1.5 px-3 py-2 border border-gray-200 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        {{ $week->motto ? 'Ändern' : 'Eintragen' }}
                    </button>
                    @endif
                </div>

                {{-- Eingabe-Formular --}}
                @if(!$isPast || $isCurrent)
                <div x-show="editing" x-transition class="mt-4 p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-3">
                    {{-- KI-Generierung --}}
                    <div class="flex items-center gap-3">
                        <button type="button"
                                @click="aiLoading = true; fetch('{{ route('swimmer.motto.generate-ai', $week) }}')
                                    .then(r => r.json())
                                    .then(d => { if(d.motto) { generatedText = d.motto; } else { alert(d.error || 'Fehler'); } aiLoading = false; })
                                    .catch(() => { alert('Netzwerkfehler'); aiLoading = false; })"
                                :disabled="aiLoading"
                                class="flex items-center gap-2 px-3 py-2 bg-violet-600 text-white text-xs font-medium rounded-lg hover:bg-violet-700 transition-colors disabled:opacity-50">
                            <svg class="w-3.5 h-3.5" :class="aiLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            <span x-text="aiLoading ? 'Generiere...' : 'KI-Motto generieren'"></span>
                        </button>
                        <p class="text-xs text-gray-400">Lässt Claude ein passendes Motto für dich vorschlagen</p>
                    </div>

                    {{-- KI-Vorschlag anzeigen --}}
                    <div x-show="generatedText !== ''" x-transition class="p-3 bg-violet-50 border border-violet-200 rounded-lg">
                        <p class="text-xs text-violet-600 font-semibold mb-1">KI-Vorschlag:</p>
                        <p class="text-sm text-violet-800 font-medium" x-text="generatedText"></p>
                        <button type="button"
                                @click="$nextTick(() => { $refs.mottoTextarea{{ $week->id }}.value = generatedText; })"
                                class="mt-2 text-xs text-violet-700 hover:underline font-medium">
                            Diesen Vorschlag verwenden →
                        </button>
                    </div>

                    <form method="POST" action="{{ route('swimmer.motto.save', $week) }}">
                        @csrf
                        <div class="space-y-2">
                            <label class="block text-xs font-medium text-gray-700">Dein Motto für KW {{ $week->week_start->weekOfYear }}</label>
                            <textarea name="motto" rows="3" maxlength="500" required
                                      x-ref="mottoTextarea{{ $week->id }}"
                                      placeholder="Schreib ein motivierendes Motto für deine Gruppe... (max. 500 Zeichen)"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none">{{ $week->motto }}</textarea>
                            <p class="text-xs text-gray-400">Maximal 500 Zeichen</p>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <button type="submit"
                                    class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                                Motto speichern
                            </button>
                            <button type="button" @click="editing = false; generatedText = ''"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-100">
                                Abbrechen
                            </button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Alle Wochen (ausklappbar) ──────────────────────────────────────────── --}}
    @if($weeks->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100" x-data="{ open: false }">
        <div class="flex items-center justify-between p-5 cursor-pointer" @click="open = !open">
            <div>
                <h2 class="font-semibold text-gray-800">Alle Wochen der Gruppe</h2>
                <p class="text-xs text-gray-400 mt-0.5">Vollständiger Jahresplan</p>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </div>
        <div x-show="open" x-transition class="border-t border-gray-100">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">KW / Datum</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Gruppe</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Zuständig</th>
                            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide px-5 py-3">Motto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($weeks as $week)
                        @php
                            $mondayCurrent = now()->startOfWeek(\Carbon\Carbon::MONDAY)->startOfDay();
                            $isCurrentWeek = $week->week_start->eq($mondayCurrent);
                            $isPast        = $week->week_start->lt($mondayCurrent);
                            $isMe          = $week->user_id === auth()->id();
                        @endphp
                        <tr class="{{ $isCurrentWeek ? 'bg-blue-50/60 font-medium' : ($isMe ? 'bg-amber-50/40' : 'hover:bg-gray-50') }} transition-colors">
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <span class="font-semibold text-gray-700">KW {{ $week->week_start->weekOfYear }}</span>
                                <span class="text-gray-400 text-xs ml-1">{{ $week->week_start->format('d.m.Y') }}</span>
                                @if($isCurrentWeek)
                                    <span class="ml-1 text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium">Jetzt</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5">
                                @php $gc = \App\Models\TrainingGroup::COLORS[$week->group->color ?? 'blue'] ?? \App\Models\TrainingGroup::COLORS['blue']; @endphp
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $gc['badge'] }}">{{ $week->group->name }}</span>
                            </td>
                            <td class="px-5 py-2.5 text-gray-600 text-xs">
                                @if($week->user)
                                    @if($isMe)
                                        <span class="font-semibold text-amber-700">{{ $week->user->firstname }} {{ $week->user->lastname }}</span>
                                        <span class="text-amber-500 ml-1 text-[10px]">Du</span>
                                    @else
                                        {{ $week->user->firstname }} {{ $week->user->lastname }}
                                    @endif
                                @else
                                    <span class="text-gray-300">–</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 max-w-xs">
                                @if($week->motto)
                                    <p class="text-xs text-gray-600 line-clamp-1">"{{ $week->motto }}"</p>
                                @else
                                    <span class="text-xs text-gray-300 italic">{{ $isPast ? 'Nicht eingetragen' : 'Ausstehend' }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @endif {{-- end groupIds not empty --}}

</div>
@endsection
