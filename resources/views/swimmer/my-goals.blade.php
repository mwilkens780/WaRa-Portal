@extends('layouts.app')
@section('title', 'Meine Ziele')
@section('page-title', 'Meine Ziele')

@section('content')
<div class="mt-2 space-y-5" x-data="goalsPage()">

    {{-- Celebration modal (manual evaluation) --}}
    <div x-show="celebrating" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
         @click.self="celebrating = false">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full mx-4 text-center"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="text-6xl mb-4">🏆</div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Glückwunsch!</h2>
            <p class="text-gray-500 mb-2">Du hast dein Ziel erreicht:</p>
            <p class="font-bold text-primary text-lg mb-5 px-4" x-text="celebratedGoal"></p>
            <div class="text-3xl mb-6">⭐ 🎉 ⭐</div>
            <button @click="celebrating = false"
                    class="w-full py-3 bg-primary text-white rounded-xl font-bold text-sm hover:bg-primary-700 transition-colors">
                Weiter so! 💪
            </button>
        </div>
    </div>

    {{-- Auto-achievement notification (from next-login check) --}}
    @if(session('auto_achieved'))
        <div class="bg-green-50 border border-green-300 rounded-xl px-5 py-4">
            <p class="text-sm font-bold text-green-700 mb-1">🎉 Automatisch erreichtes Ziel!</p>
            @foreach(session('auto_achieved') as $title)
                <p class="text-sm text-green-600">→ {{ $title }}</p>
            @endforeach
            <p class="text-xs text-green-500 mt-1">Deine Trainingszeit hat das Ziel automatisch erfüllt.</p>
        </div>
    @endif

    {{-- Quote of the day --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 flex items-start gap-3">
        <span class="text-amber-400 text-lg flex-shrink-0 mt-0.5">✦</span>
        <p class="text-sm text-amber-700 italic leading-relaxed">"{{ $quote }}"</p>
    </div>

    {{-- Season selector --}}
    <div class="flex items-center gap-2 flex-wrap">
        @foreach($seasons as $s)
            <a href="{{ route('swimmer.goals.index', ['season_id' => $s->id]) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                      {{ $activeSeason?->id == $s->id
                          ? 'bg-primary text-white shadow-sm'
                          : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                {{ $s->label ?? $s->name }}
            </a>
        @endforeach
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Add goal --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ open: false, type: 'time' }">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-gray-50 transition-colors">
            <span class="font-semibold text-gray-800 text-sm">+ Neues Ziel hinzufügen</span>
            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" x-cloak class="border-t border-gray-100 px-5 py-5">
            <form method="POST" action="{{ route('swimmer.goals.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="season_id" value="{{ $activeSeason?->id }}">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Zieltyp</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach(['time' => 'Zeit-Ziel', 'qualification' => 'Qualifikation', 'free' => 'Freies Ziel'] as $val => $lbl)
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="{{ $val }}" x-model="type" class="sr-only">
                                <span class="block text-center px-2 py-2.5 rounded-lg border text-xs font-semibold transition-colors select-none"
                                      :class="type === '{{ $val }}'
                                          ? 'bg-primary text-white border-primary'
                                          : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                                    {{ $lbl }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Titel</label>
                    <input type="text" name="title" required maxlength="255"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none"
                           placeholder="z.B. 200m Freistil unter 2:10 min">
                </div>
                <div x-show="type === 'time'" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Disziplin</label>
                        <select name="discipline" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none">
                            <option value="">–</option>
                            @foreach(['F' => 'Freistil','B' => 'Brust','R' => 'Rücken','S' => 'Schmetterling','L' => 'Lagen'] as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Distanz (m)</label>
                        <input type="number" name="distance" min="25" step="25"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none" placeholder="200">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Bahn</label>
                        <select name="course" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none">
                            <option value="">–</option>
                            <option value="Kurzbahn">Kurzbahn (25 m)</option>
                            <option value="Langbahn">Langbahn (50 m)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Zielzeit</label>
                        <div class="flex items-center gap-1">
                            <input type="number" name="target_minutes" min="0" placeholder="0"
                                   class="w-12 px-1.5 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary/30 outline-none">
                            <span class="text-gray-400 font-mono">:</span>
                            <input type="number" name="target_seconds" min="0" max="59" placeholder="00"
                                   class="w-12 px-1.5 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary/30 outline-none">
                            <span class="text-gray-400 font-mono">,</span>
                            <input type="number" name="target_centiseconds" min="0" max="99" placeholder="00"
                                   class="w-12 px-1.5 py-2 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary/30 outline-none">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Notizen <span class="font-normal normal-case text-gray-400">(optional)</span></label>
                    <textarea name="notes" rows="2" maxlength="1000"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none resize-none"
                              placeholder="Weitere Details oder Motivation..."></textarea>
                </div>
                <button type="submit" class="px-5 py-2.5 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary-700 transition-colors">
                    Ziel speichern
                </button>
            </form>
        </div>
    </div>

    {{-- Goals by type --}}
    @php
        $byType = [
            'time'          => ['label' => 'Zeit-Ziele',     'color' => 'bg-blue-100 text-blue-700',    'goals' => $goals->where('type','time')],
            'qualification' => ['label' => 'Qualifikationen','color' => 'bg-violet-100 text-violet-700','goals' => $goals->where('type','qualification')],
            'free'          => ['label' => 'Freie Ziele',    'color' => 'bg-teal-100 text-teal-700',    'goals' => $goals->where('type','free')],
        ];
    @endphp

    @foreach($byType as $type => $section)
        @if($section['goals']->isNotEmpty())
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $section['color'] }}">{{ $section['label'] }}</span>
                <span class="text-xs text-gray-400">
                    {{ $section['goals']->where('achieved', true)->count() }}/{{ $section['goals']->count() }} erreicht
                </span>
            </div>

            <div class="space-y-2">
                @foreach($section['goals'] as $goal)
                @php
                    $status    = $goal->status ?? 'open';
                    $isDone    = $status !== 'open';
                    $isOpen    = $status === 'open';
                    $key       = $goal->discipline . '_' . $goal->distance;
                    $seasonBest = $seasonBests[$key] ?? null;
                @endphp
                <div class="bg-white rounded-xl shadow-sm border
                        {{ $goal->achieved ? 'border-green-200 bg-green-50/20' : ($isDone ? 'border-gray-200 opacity-70' : 'border-gray-100') }}
                        overflow-hidden"
                     x-data="{ evalOpen: false, evalStatus: 'achieved' }">
                    <div class="px-5 py-4">
                        <div class="flex items-start gap-3">

                            {{-- Status icon --}}
                            <div class="flex-shrink-0 mt-0.5">
                                @if($goal->achieved)
                                    <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                @elseif($status === 'not_achieved')
                                    <div class="w-6 h-6 bg-red-100 rounded-full flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </div>
                                @elseif($status === 'cancelled')
                                    <div class="w-6 h-6 bg-gray-200 rounded-full flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-6 h-6 border-2 border-gray-200 rounded-full"></div>
                                @endif
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold
                                           {{ $goal->achieved ? 'text-green-700' : ($isDone ? 'text-gray-400' : 'text-gray-800') }}">
                                        {{ $goal->title }}
                                    </p>
                                    @if($goal->achieved)
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Erreicht ✓</span>
                                    @elseif($status === 'not_achieved')
                                        <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-semibold">Nicht erreicht</span>
                                    @elseif($status === 'cancelled')
                                        <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full font-semibold">Abgebrochen</span>
                                    @endif
                                </div>

                                {{-- Time goal details --}}
                                @if($goal->type === 'time')
                                    <div class="mt-1 space-y-0.5">
                                        @if($goal->distance || $goal->discipline_label || $goal->formatted_target_time)
                                        <p class="text-xs text-gray-400">
                                            @if($goal->distance){{ $goal->distance }} m @endif
                                            @if($goal->discipline_label){{ $goal->discipline_label }}@endif
                                            @if($goal->course) · {{ $goal->course }}@endif
                                            @if($goal->formatted_target_time) · Ziel: <span class="font-mono font-semibold">{{ $goal->formatted_target_time }}</span>@endif
                                            @if($goal->achieved && $goal->formatted_achieved_time)
                                                · Erreicht: <span class="font-mono font-semibold text-green-600">{{ $goal->formatted_achieved_time }}</span>
                                            @endif
                                        </p>
                                        @endif
                                        {{-- Season best --}}
                                        @if($seasonBest)
                                        <p class="text-xs">
                                            @if($seasonBest['formatted'])
                                                <span class="text-primary font-semibold font-mono">{{ $seasonBest['formatted'] }}</span>
                                                <span class="text-gray-400"> Saisonbestzeit</span>
                                                @if($goal->formatted_target_time)
                                                    @php
                                                        $diff = $seasonBest['best_ms'] - $goal->target_time_ms;
                                                        $sign = $diff < 0 ? '−' : '+';
                                                        $absDiff = abs($diff);
                                                        $s = intdiv($absDiff, 1000);
                                                        $cs = intdiv($absDiff % 1000, 10);
                                                    @endphp
                                                    <span class="ml-1 font-semibold {{ $diff <= 0 ? 'text-green-600' : 'text-gray-500' }}">
                                                        ({{ $sign }}{{ $s }},{{ str_pad($cs, 2, '0', STR_PAD_LEFT) }} s)
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-gray-400 italic">Noch keine Zeit in dieser Saison</span>
                                            @endif
                                            @if($seasonBest['count'] > 0)
                                                <span class="ml-2 text-gray-400">· {{ $seasonBest['count'] }} Wertung{{ $seasonBest['count'] !== 1 ? 'en' : '' }}</span>
                                            @endif
                                        </p>
                                        @endif
                                    </div>
                                @endif

                                @if($goal->achieved_at && $goal->type !== 'time')
                                    <p class="text-xs text-green-600 mt-0.5">Am {{ $goal->achieved_at->format('d.m.Y') }} erreicht</p>
                                @endif
                                @if($goal->notes)
                                    <p class="text-xs text-gray-400 mt-1 italic">{{ $goal->notes }}</p>
                                @endif

                                {{-- Progress bar for free goals --}}
                                @if($goal->type === 'free' && $isOpen)
                                    <div class="mt-3" x-data="{ progress: {{ $goal->progress ?? 0 }} }">
                                        <div class="flex items-center justify-between mb-1.5">
                                            <span class="text-xs text-gray-500">Fortschritt</span>
                                            <span class="text-xs font-bold text-primary" x-text="progress + ' %'"></span>
                                        </div>
                                        <input type="range" min="0" max="100" step="5"
                                               x-model="progress"
                                               @change="saveProgress({{ $goal->id }}, progress)"
                                               class="w-full h-2 rounded-full appearance-none cursor-pointer accent-primary bg-gray-200">
                                    </div>
                                @endif

                                {{-- Trainer comments --}}
                                @if($goal->comments->isNotEmpty())
                                    <div class="mt-3 space-y-1.5">
                                        @foreach($goal->comments as $comment)
                                            <div class="bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
                                                <p class="text-xs font-semibold text-blue-700">{{ $comment->trainer->name }}</p>
                                                <p class="text-xs text-blue-600 mt-0.5 leading-relaxed">{{ $comment->comment }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                @if($isOpen)
                                    <button type="button" @click="evalOpen = !evalOpen"
                                            class="text-xs px-3 py-1.5 bg-primary/10 text-primary border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors font-semibold whitespace-nowrap">
                                        Bewerten
                                    </button>
                                @elseif($isDone)
                                    <button type="button" @click="evalOpen = !evalOpen"
                                            class="text-xs px-2.5 py-1.5 border border-gray-200 text-gray-400 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                                        Ändern
                                    </button>
                                @endif
                                <form method="POST" action="{{ route('swimmer.goals.destroy', $goal) }}"
                                      onsubmit="return confirm('Dieses Ziel wirklich löschen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-400 transition-colors rounded-lg hover:bg-red-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Evaluate form --}}
                        <div x-show="evalOpen" x-cloak class="mt-4 pt-4 border-t border-gray-100">
                            <form method="POST" action="{{ route('swimmer.goals.evaluate', $goal) }}" class="space-y-3">
                                @csrf

                                {{-- Status options --}}
                                <div>
                                    <p class="text-xs font-semibold text-gray-600 mb-2">Wie ist es gelaufen?</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="achieved" x-model="evalStatus" class="sr-only">
                                            <span class="block text-center px-2 py-2 rounded-lg border text-xs font-semibold transition-colors select-none"
                                                  :class="evalStatus === 'achieved' ? 'bg-green-600 text-white border-green-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                                                ✓ Erreicht
                                            </span>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="not_achieved" x-model="evalStatus" class="sr-only">
                                            <span class="block text-center px-2 py-2 rounded-lg border text-xs font-semibold transition-colors select-none"
                                                  :class="evalStatus === 'not_achieved' ? 'bg-red-500 text-white border-red-500' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                                                ✗ Nicht erreicht
                                            </span>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="status" value="cancelled" x-model="evalStatus" class="sr-only">
                                            <span class="block text-center px-2 py-2 rounded-lg border text-xs font-semibold transition-colors select-none"
                                                  :class="evalStatus === 'cancelled' ? 'bg-gray-500 text-white border-gray-500' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                                                ⊘ Abgebrochen
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                {{-- Achieved time (for time goals when achieved) --}}
                                @if($goal->type === 'time')
                                    <div x-show="evalStatus === 'achieved'">
                                        <p class="text-xs font-semibold text-gray-600 mb-1.5">Erreichte Zeit <span class="font-normal text-gray-400">(optional)</span></p>
                                        @if($seasonBest && $seasonBest['formatted'])
                                            <p class="text-xs text-primary mb-2">
                                                Deine Saisonbestzeit: <span class="font-mono font-bold">{{ $seasonBest['formatted'] }}</span>
                                                @if($seasonBest['count'] > 0)
                                                    · {{ $seasonBest['count'] }} Wertung{{ $seasonBest['count'] !== 1 ? 'en' : '' }}
                                                @endif
                                            </p>
                                        @endif
                                        <div class="flex items-center gap-1.5">
                                            <input type="number" name="achieved_minutes" min="0" placeholder="0"
                                                   class="w-14 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary/30 outline-none">
                                            <span class="text-gray-400 font-mono text-sm">:</span>
                                            <input type="number" name="achieved_seconds" min="0" max="59" placeholder="00"
                                                   class="w-14 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary/30 outline-none">
                                            <span class="text-gray-400 font-mono text-sm">,</span>
                                            <input type="number" name="achieved_centiseconds" min="0" max="99" placeholder="00"
                                                   class="w-14 px-2 py-1.5 border border-gray-300 rounded-lg text-sm text-center focus:ring-2 focus:ring-primary/30 outline-none">
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center gap-2">
                                    <button type="submit"
                                            class="px-4 py-2 text-white rounded-lg text-sm font-bold transition-colors"
                                            :class="{
                                                'bg-green-600 hover:bg-green-700': evalStatus === 'achieved',
                                                'bg-red-500 hover:bg-red-600': evalStatus === 'not_achieved',
                                                'bg-gray-500 hover:bg-gray-600': evalStatus === 'cancelled'
                                            }">
                                        Speichern
                                    </button>
                                    <button type="button" @click="evalOpen = false"
                                            class="px-3 py-2 border border-gray-200 text-gray-500 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                                        Abbrechen
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    @endforeach

    @if($goals->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-14 text-center">
            <div class="text-4xl mb-3">🎯</div>
            <p class="font-semibold text-gray-500">Noch keine Ziele für diese Saison</p>
            <p class="text-sm text-gray-400 mt-1">Klicke auf "+ Neues Ziel hinzufügen" um zu starten!</p>
        </div>
    @endif

</div>

@push('scripts')
<script>
function goalsPage() {
    return {
        celebrating: {{ session()->has('just_achieved') ? 'true' : 'false' }},
        celebratedGoal: @json(session('just_achieved', '')),
        saveProgress(goalId, progress) {
            fetch(`{{ url('/schwimmer/meine-ziele') }}/${goalId}/fortschritt`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ progress: parseInt(progress) })
            });
        }
    }
}
</script>
@endpush
@endsection
