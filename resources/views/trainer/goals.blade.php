@extends('layouts.app')
@section('title', 'Ziele')
@section('page-title', 'Ziele')

@section('content')
<div class="mt-2 space-y-5" x-data="{ activeGroup: {{ $groups->first()?->id ?? 'null' }} }">

    {{-- Season selector --}}
    <div class="flex items-center gap-2 flex-wrap">
        @foreach($seasons as $s)
            <a href="{{ route('trainer.goals.index', ['season_id' => $s->id]) }}"
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

    @if($groups->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Keine Trainingsgruppen zugewiesen.
        </div>
    @else

    {{-- Group tabs --}}
    <div class="flex gap-2 flex-wrap">
        @foreach($groups as $group)
            @php
                $gGoalCount = ($groupGoals[$group->id] ?? collect())->count();
                $swimmerGoalCount = $group->swimmers->sum(fn($s) => ($goalsBySwimmer[$s->id] ?? collect())->count());
            @endphp
            <button type="button"
                    @click="activeGroup = {{ $group->id }}"
                    :class="activeGroup === {{ $group->id }}
                        ? 'bg-primary text-white shadow-sm'
                        : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2">
                {{ $group->name }}
                @if($swimmerGoalCount > 0)
                    <span class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                          :class="activeGroup === {{ $group->id }} ? 'bg-white/20 text-white' : 'bg-primary/10 text-primary'">
                        {{ $swimmerGoalCount }}
                    </span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Group panels --}}
    @foreach($groups as $group)
        @php
            $gGoals = $groupGoals[$group->id] ?? collect();
        @endphp
        <div x-show="activeGroup === {{ $group->id }}" x-cloak class="space-y-4">

            {{-- Group Goals --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" x-data="{ addOpen: false }">
                <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-gray-700 text-sm">Gruppenziele</h3>
                        @if($gGoals->isNotEmpty())
                            <span class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-semibold">
                                {{ $gGoals->where('achieved', true)->count() }}/{{ $gGoals->count() }} erreicht
                            </span>
                        @endif
                    </div>
                    <button type="button" @click="addOpen = !addOpen"
                            class="text-xs px-3 py-1.5 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors font-semibold">
                        + Ziel hinzufügen
                    </button>
                </div>

                {{-- Add group goal form --}}
                <div x-show="addOpen" x-cloak class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                    <form method="POST" action="{{ route('trainer.group-goals.store') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="training_group_id" value="{{ $group->id }}">
                        <input type="hidden" name="season_id" value="{{ $activeSeason?->id }}">
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Titel</label>
                                <input type="text" name="title" required maxlength="255"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none"
                                       placeholder="z.B. NDM-Teilnahme mit 5 Startern">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Zielanzahl <span class="font-normal text-gray-400">(optional)</span></label>
                                <input type="number" name="target_count" min="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none"
                                       placeholder="z.B. 5">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Beschreibung <span class="font-normal text-gray-400">(optional)</span></label>
                            <textarea name="description" rows="2" maxlength="1000"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none resize-none"
                                      placeholder="Details..."></textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit"
                                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary-700 transition-colors">
                                Speichern
                            </button>
                            <button type="button" @click="addOpen = false"
                                    class="px-3 py-2 border border-gray-200 text-gray-500 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                                Abbrechen
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Group goals list --}}
                @if($gGoals->isEmpty())
                    <p class="px-5 py-4 text-sm text-gray-400">Noch keine Gruppenziele definiert.</p>
                @else
                    @foreach($gGoals as $gg)
                        <div class="px-5 py-3 border-t border-gray-50 first:border-t-0"
                             x-data="{ editOpen: false }">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 mt-0.5">
                                    @if($gg->achieved)
                                        <div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="w-5 h-5 border-2 border-gray-200 rounded-full"></div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold {{ $gg->achieved ? 'text-green-700' : 'text-gray-800' }}">
                                        {{ $gg->title }}
                                        @if($gg->achieved)
                                            <span class="ml-1.5 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Erreicht</span>
                                        @endif
                                    </p>
                                    @if($gg->description)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $gg->description }}</p>
                                    @endif
                                    @if($gg->target_count)
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            Ziel: {{ $gg->target_count }}
                                            @if($gg->achieved_count) · Erreicht: <span class="font-semibold">{{ $gg->achieved_count }}</span>@endif
                                        </p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <button type="button" @click="editOpen = !editOpen"
                                            class="text-xs px-2.5 py-1.5 border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-50 transition-colors">
                                        Bearbeiten
                                    </button>
                                    <form method="POST" action="{{ route('trainer.group-goals.destroy', $gg) }}"
                                          onsubmit="return confirm('Gruppenziel löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-400 transition-colors rounded-lg hover:bg-red-50">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Edit form --}}
                            <div x-show="editOpen" x-cloak class="mt-3 pt-3 border-t border-gray-100">
                                <form method="POST" action="{{ route('trainer.group-goals.update', $gg) }}" class="flex items-end gap-3 flex-wrap">
                                    @csrf @method('PUT')
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 mb-1">Erreicht (Anzahl)</label>
                                        <input type="number" name="achieved_count" min="0"
                                               value="{{ $gg->achieved_count }}"
                                               class="w-24 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="hidden" name="achieved" value="0">
                                            <input type="checkbox" name="achieved" value="1"
                                                   {{ $gg->achieved ? 'checked' : '' }}
                                                   class="w-4 h-4 text-primary rounded accent-primary">
                                            <span class="text-sm text-gray-700 font-medium">Als erreicht markieren</span>
                                        </label>
                                    </div>
                                    <button type="submit"
                                            class="px-4 py-1.5 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary-700 transition-colors">
                                        Speichern
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Swimmer goals --}}
            @if($group->swimmers->isEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-6 text-center text-sm text-gray-400">
                    Keine aktiven Schwimmer in dieser Gruppe.
                </div>
            @else
                @foreach($group->swimmers as $swimmer)
                    @php
                        $swimGoals = $goalsBySwimmer[$swimmer->id] ?? collect();
                        $achieved  = $swimGoals->where('achieved', true)->count();
                        $total     = $swimGoals->count();
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        {{-- Swimmer header --}}
                        <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary flex-shrink-0">
                                    {{ strtoupper(substr($swimmer->firstname, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">{{ $swimmer->lastname }}, {{ $swimmer->firstname }}</p>
                                </div>
                            </div>
                            @if($total > 0)
                                <div class="flex items-center gap-2">
                                    <div class="w-20 bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $total > 0 ? round($achieved/$total*100) : 0 }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 font-medium whitespace-nowrap">{{ $achieved }}/{{ $total }}</span>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">Keine Ziele</span>
                            @endif
                        </div>

                        @if($swimGoals->isEmpty())
                            <p class="px-5 py-4 text-sm text-gray-400 italic">Noch keine Ziele definiert.</p>
                        @else
                            @php
                                $typeLabels = ['time' => 'Zeit', 'qualification' => 'Qualifikation', 'free' => 'Frei'];
                                $typeColors = ['time' => 'bg-blue-100 text-blue-700', 'qualification' => 'bg-violet-100 text-violet-700', 'free' => 'bg-teal-100 text-teal-700'];
                            @endphp
                            @foreach($swimGoals as $goal)
                                @php $myComment = $goal->comments->firstWhere('trainer_id', auth()->id()); @endphp
                                <div class="px-5 py-3 border-t border-gray-50 first:border-t-0" x-data="{ commentOpen: false }">
                                    <div class="flex items-start gap-3">
                                        {{-- Status --}}
                                        <div class="flex-shrink-0 mt-0.5">
                                            @if($goal->achieved)
                                                <div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </div>
                                            @else
                                                <div class="w-5 h-5 border-2 border-gray-200 rounded-full"></div>
                                            @endif
                                        </div>

                                        {{-- Goal content --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $typeColors[$goal->type] }}">
                                                    {{ $typeLabels[$goal->type] }}
                                                </span>
                                                <p class="text-sm {{ $goal->achieved ? 'font-bold text-green-700' : 'font-semibold text-gray-700' }}">
                                                    {{ $goal->title }}
                                                </p>
                                                @if($goal->achieved)
                                                    <span class="text-xs bg-green-100 text-green-600 px-1.5 py-0.5 rounded-full">✓</span>
                                                @endif
                                            </div>

                                            @if($goal->type === 'time' && ($goal->discipline || $goal->distance))
                                                <p class="text-xs text-gray-400 mt-0.5">
                                                    @if($goal->distance){{ $goal->distance }} m @endif
                                                    @if($goal->discipline_label){{ $goal->discipline_label }}@endif
                                                    @if($goal->course) · {{ $goal->course }}@endif
                                                    @if($goal->formatted_target_time) · Ziel: <span class="font-mono">{{ $goal->formatted_target_time }}</span>@endif
                                                    @if($goal->achieved && $goal->formatted_achieved_time)
                                                        · <span class="font-mono font-bold text-green-600">{{ $goal->formatted_achieved_time }}</span>
                                                    @endif
                                                </p>
                                            @endif

                                            @if($goal->type === 'free' && !$goal->achieved && $goal->progress > 0)
                                                <div class="flex items-center gap-2 mt-1.5">
                                                    <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                                                        <div class="bg-teal-500 h-1.5 rounded-full" style="width: {{ $goal->progress }}%"></div>
                                                    </div>
                                                    <span class="text-xs text-gray-500 font-medium">{{ $goal->progress }} %</span>
                                                </div>
                                            @endif

                                            @if($goal->notes)
                                                <p class="text-xs text-gray-400 mt-0.5 italic">{{ $goal->notes }}</p>
                                            @endif

                                            {{-- Existing comments (from other trainers) --}}
                                            @foreach($goal->comments->where('trainer_id', '!=', auth()->id()) as $comment)
                                                <div class="mt-2 bg-blue-50 border border-blue-100 rounded-lg px-2.5 py-1.5">
                                                    <p class="text-xs text-blue-600"><span class="font-semibold">{{ $comment->trainer->name }}:</span> {{ $comment->comment }}</p>
                                                </div>
                                            @endforeach

                                            {{-- My comment (if exists, show inline) --}}
                                            @if($myComment)
                                                <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 flex items-start justify-between gap-2">
                                                    <p class="text-xs text-amber-700 leading-relaxed">
                                                        <span class="font-semibold">Mein Kommentar:</span> {{ $myComment->comment }}
                                                    </p>
                                                    <button type="button" @click="commentOpen = true"
                                                            class="text-xs text-amber-600 hover:text-amber-800 whitespace-nowrap font-medium">
                                                        Bearbeiten
                                                    </button>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Comment button --}}
                                        @if(!$myComment)
                                            <button type="button" @click="commentOpen = !commentOpen"
                                                    class="flex-shrink-0 text-xs px-2.5 py-1.5 border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                                                + Kommentar
                                            </button>
                                        @endif
                                    </div>

                                    {{-- Comment form --}}
                                    <div x-show="commentOpen" x-cloak class="mt-3 pt-3 border-t border-gray-100">
                                        <form method="POST" action="{{ route('trainer.goals.comment', $goal) }}" class="flex gap-2">
                                            @csrf
                                            <textarea name="comment" rows="2" required maxlength="1000"
                                                      class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none resize-none"
                                                      placeholder="Kommentar eingeben...">{{ $myComment?->comment }}</textarea>
                                            <div class="flex flex-col gap-1.5">
                                                <button type="submit"
                                                        class="px-3 py-2 bg-primary text-white rounded-lg text-xs font-semibold hover:bg-primary-700 transition-colors">
                                                    Speichern
                                                </button>
                                                <button type="button" @click="commentOpen = false"
                                                        class="px-3 py-2 border border-gray-200 text-gray-500 rounded-lg text-xs hover:bg-gray-50 transition-colors">
                                                    Abbrechen
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach

    @endif
</div>
@endsection
