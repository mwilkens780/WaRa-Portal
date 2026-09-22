@extends('layouts.app')
@section('title', 'Ziele')
@section('page-title', 'Ziele')

@php
    $statusLabels = \App\Models\TrainingGroupGoal::$statusLabels;
    $statusBadges = \App\Models\TrainingGroupGoal::$statusBadges;
    $typeLabels   = ['time' => 'Zeit', 'qualification' => 'Qualifikation', 'free' => 'Frei'];
    $typeColors   = ['time' => 'bg-blue-100 text-blue-700', 'qualification' => 'bg-violet-100 text-violet-700', 'free' => 'bg-teal-100 text-teal-700'];
@endphp

@section('content')
<div class="mt-2 space-y-5" x-data="keep('goals-group', {{ $groups->first()?->id ?? 'null' }}, @js($groups->pluck('id')->values()))">

    {{-- Saisonauswahl --}}
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

    @if(!$activeSeason)
        <div class="px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            Es ist keine Saison angelegt. Bewertungen sind erst möglich, wenn eine Saison existiert.
        </div>
    @endif

    @if($groups->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Keine Trainingsgruppen zugewiesen.
        </div>
    @else

    {{-- Gruppenauswahl --}}
    <div class="flex gap-2 flex-wrap">
        @foreach($groups as $group)
            <button type="button"
                    @click="open = {{ $group->id }}"
                    :class="open === {{ $group->id }}
                        ? 'bg-primary text-white shadow-sm'
                        : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                {{ $group->name }}
            </button>
        @endforeach
    </div>

    @foreach($groups as $group)
        @php
            $groupCriteria = $criteria[$group->id] ?? collect();
            $swimmers      = $group->swimmers;
            $swimmerCount  = $swimmers->count();
        @endphp
        <div x-show="open === {{ $group->id }}" x-cloak class="space-y-4">

            {{-- ═══ Leistungskriterien ═══════════════════════════════════════ --}}
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
                     x-data="keep('goals-crit-{{ $group->id }}', true)">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-90' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-semibold text-gray-800">Leistungskriterien</h2>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Entscheiden über Verbleib oder Wechsel der Gruppe · jede Saison neu zu erfüllen
                        </p>
                    </div>
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium flex-shrink-0">
                        {{ $groupCriteria->count() }} {{ $groupCriteria->count() === 1 ? 'Kriterium' : 'Kriterien' }}
                    </span>
                </button>

                <div x-show="open" x-cloak class="border-t border-gray-100">

                    {{-- Neues Kriterium --}}
                    <div class="px-5 py-3 bg-gray-50/60 border-b border-gray-100" x-data="{ add: false }">
                        <button type="button" @click="add = !add" x-show="!add"
                                class="text-xs px-3 py-1.5 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors font-semibold">
                            + Leistungskriterium
                        </button>
                        <form x-show="add" x-cloak method="POST" action="{{ route('trainer.group-goals.store') }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="training_group_id" value="{{ $group->id }}">
                            <div class="grid sm:grid-cols-3 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Kriterium</label>
                                    <input type="text" name="title" required maxlength="255"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none"
                                           placeholder="z.B. 80 % Trainingsbeteiligung">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Zielwert <span class="font-normal text-gray-400">(optional)</span></label>
                                    <input type="text" name="target_value" maxlength="255"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none"
                                           placeholder="z.B. 80 %">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Beschreibung <span class="font-normal text-gray-400">(optional)</span></label>
                                <textarea name="description" rows="2" maxlength="1000"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none resize-none"></textarea>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary-700 transition-colors">Speichern</button>
                                <button type="button" @click="add = false" class="px-3 py-2 border border-gray-200 text-gray-500 rounded-lg text-sm hover:bg-gray-50 transition-colors">Abbrechen</button>
                            </div>
                        </form>
                    </div>

                    @if($groupCriteria->isEmpty())
                        <p class="px-5 py-6 text-sm text-gray-400 text-center">Für diese Gruppe sind noch keine Leistungskriterien festgelegt.</p>
                    @endif

                    <div class="divide-y divide-gray-100">
                    @foreach($groupCriteria as $crit)
                        @php
                            $trainerEvs = $crit->evaluations->where('evaluation_type', 'trainer')->keyBy('user_id');
                            $selfEvs    = $crit->evaluations->where('evaluation_type', 'self')->keyBy('user_id');
                            $cntYes  = $swimmers->filter(fn($s) => $trainerEvs->get($s->id)?->achieved === true)->count();
                            $cntNo   = $swimmers->filter(fn($s) => $trainerEvs->get($s->id)?->achieved === false)->count();
                            $cntOpen = $swimmerCount - $cntYes - $cntNo;
                        @endphp
                        <div x-data="keep('goals-c-{{ $crit->id }}', false)">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center gap-3 px-5 py-3 text-left hover:bg-gray-50 transition-colors">
                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm font-semibold text-gray-800">{{ $crit->title }}</span>
                                    @if($crit->target_value)
                                        <span class="ml-1.5 text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Ziel: {{ $crit->target_value }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0 text-[11px] font-semibold">
                                    <span class="px-1.5 py-0.5 rounded bg-green-100 text-green-700" title="Erreicht">✓ {{ $cntYes }}</span>
                                    <span class="px-1.5 py-0.5 rounded bg-red-100 text-red-700" title="Nicht erreicht">✗ {{ $cntNo }}</span>
                                    <span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-500" title="Noch nicht bewertet">– {{ $cntOpen }}</span>
                                </div>
                            </button>

                            <div x-show="open" x-cloak class="px-5 pb-4" x-data="{ edit: false, evaluating: null }">

                                {{-- Beschreibung + Verwaltung --}}
                                <div class="flex items-start gap-3 mb-3">
                                    <p class="flex-1 text-xs text-gray-500">{{ $crit->description }}</p>
                                    <button type="button" @click="edit = !edit"
                                            class="text-xs px-2.5 py-1 border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-50 transition-colors">
                                        Bearbeiten
                                    </button>
                                    <form method="POST" action="{{ route('trainer.group-goals.destroy', $crit) }}"
                                          onsubmit="return confirm('Leistungskriterium „{{ addslashes($crit->title) }}“ löschen?\n\nDamit werden auch alle Bewertungen aus allen Saisons gelöscht.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs px-2.5 py-1 border border-gray-200 text-gray-400 rounded-lg hover:text-red-500 hover:border-red-200 transition-colors">
                                            Löschen
                                        </button>
                                    </form>
                                </div>

                                <form x-show="edit" x-cloak method="POST" action="{{ route('trainer.group-goals.update', $crit) }}"
                                      class="mb-4 p-3 bg-gray-50 rounded-lg space-y-2">
                                    @csrf @method('PUT')
                                    <div class="grid sm:grid-cols-3 gap-2">
                                        <input type="text" name="title" value="{{ $crit->title }}" required maxlength="255"
                                               class="sm:col-span-2 px-3 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                                        <input type="text" name="target_value" value="{{ $crit->target_value }}" maxlength="255" placeholder="Zielwert"
                                               class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                                    </div>
                                    <textarea name="description" rows="2" maxlength="1000" placeholder="Beschreibung"
                                              class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30 resize-none">{{ $crit->description }}</textarea>
                                    <button type="submit" class="px-3 py-1.5 bg-primary text-white rounded-lg text-xs font-semibold">Speichern</button>
                                </form>

                                {{-- Bewertung je Schwimmer --}}
                                @if($swimmers->isEmpty())
                                    <p class="text-sm text-gray-400">Keine aktiven Schwimmer in dieser Gruppe.</p>
                                @else
                                <div class="overflow-x-auto rounded-lg border border-gray-100">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="bg-gray-50 text-gray-500 uppercase tracking-wide text-[10px]">
                                                <th class="text-left px-3 py-2 font-semibold">Schwimmer</th>
                                                <th class="text-left px-3 py-2 font-semibold">Eigenbewertung</th>
                                                <th class="text-left px-3 py-2 font-semibold">Trainer</th>
                                                <th class="text-left px-3 py-2 font-semibold">Trainer-Notiz</th>
                                                <th class="px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                        @foreach($swimmers as $swimmer)
                                            @php
                                                $se = $selfEvs->get($swimmer->id);
                                                $te = $trainerEvs->get($swimmer->id);
                                                $seStatus = $se?->status ?? 'open';
                                                $teStatus = $te?->status ?? 'open';
                                            @endphp
                                            <tr class="hover:bg-gray-50/50">
                                                <td class="px-3 py-2 font-medium text-gray-700 whitespace-nowrap">{{ $swimmer->lastname }}, {{ $swimmer->firstname }}</td>
                                                <td class="px-3 py-2">
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusBadges[$seStatus] }}">{{ $statusLabels[$seStatus] }}</span>
                                                    @if($se?->notes)
                                                        <p class="text-gray-400 italic mt-0.5 max-w-[220px]" title="{{ $se->notes }}">{{ $se->notes }}</p>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2">
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusBadges[$teStatus] }}">{{ $statusLabels[$teStatus] }}</span>
                                                </td>
                                                <td class="px-3 py-2 text-gray-500 max-w-[260px]">{{ $te?->notes }}</td>
                                                <td class="px-3 py-2 text-right">
                                                    @if($activeSeason)
                                                    <button type="button"
                                                            @click="evaluating = evaluating === {{ $swimmer->id }} ? null : {{ $swimmer->id }}"
                                                            :class="evaluating === {{ $swimmer->id }} ? 'bg-primary/10 text-primary' : 'border border-gray-200 text-gray-500 hover:bg-gray-50'"
                                                            class="px-2.5 py-1 rounded-lg text-[10px] font-semibold transition-colors whitespace-nowrap">
                                                        Bewerten
                                                    </button>
                                                    @endif
                                                </td>
                                            </tr>
                                            @if($activeSeason)
                                            <tr x-show="evaluating === {{ $swimmer->id }}" x-cloak class="bg-primary/5">
                                                <td colspan="5" class="px-3 py-3">
                                                    <form method="POST" action="{{ route('trainer.group-goals.evaluate', [$crit, $swimmer]) }}"
                                                          class="flex flex-wrap items-center gap-3">
                                                        @csrf
                                                        <input type="hidden" name="season_id" value="{{ $activeSeason->id }}">
                                                        <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs font-semibold">
                                                            @foreach(['1' => ['Erreicht', 'peer-checked:bg-green-600'], '0' => ['Nicht erreicht', 'peer-checked:bg-red-600'], '' => ['Offen', 'peer-checked:bg-gray-500']] as $val => [$lbl, $on])
                                                                <label class="cursor-pointer">
                                                                    <input type="radio" name="achieved" value="{{ $val }}" class="peer sr-only"
                                                                           {{ ($val === '1' && $teStatus === 'achieved') || ($val === '0' && $teStatus === 'missed') || ($val === '' && $teStatus === 'open') ? 'checked' : '' }}>
                                                                    <span class="block px-3 py-1.5 bg-white text-gray-600 {{ $on }} peer-checked:text-white transition-colors">{{ $lbl }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                        <input type="text" name="notes" maxlength="1000" value="{{ $te?->notes }}"
                                                               placeholder="Trainer-Notiz"
                                                               class="flex-1 min-w-[180px] px-2 py-1.5 border border-gray-300 rounded-lg text-xs outline-none focus:ring-1 focus:ring-primary/40">
                                                        <button type="submit" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-700 transition-colors">Speichern</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            @endif
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
            </section>

            {{-- ═══ Persönliche Ziele ═════════════════════════════════════════ --}}
            @php
                $pgTotal    = $swimmers->sum(fn($s) => ($goalsBySwimmer[$s->id] ?? collect())->count());
                $pgAchieved = $swimmers->sum(fn($s) => ($goalsBySwimmer[$s->id] ?? collect())->where('achieved', true)->count());
            @endphp
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
                     x-data="keep('goals-pers-{{ $group->id }}', true)">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-90' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-semibold text-gray-800">Persönliche Ziele</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Von den Sportlern selbst gesetzt</p>
                    </div>
                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium flex-shrink-0">
                        {{ $pgAchieved }}/{{ $pgTotal }} erreicht
                    </span>
                </button>

                <div x-show="open" x-cloak class="border-t border-gray-100 divide-y divide-gray-100">
                    @if($swimmers->isEmpty())
                        <p class="px-5 py-6 text-sm text-gray-400 text-center">Keine aktiven Schwimmer in dieser Gruppe.</p>
                    @endif

                    @foreach($swimmers as $swimmer)
                        @php
                            $swimGoals = $goalsBySwimmer[$swimmer->id] ?? collect();
                            $achieved  = $swimGoals->where('achieved', true)->count();
                            $total     = $swimGoals->count();
                        @endphp
                        <div x-data="keep('goals-s-{{ $group->id }}-{{ $swimmer->id }}', false)">
                            <button type="button" @click="open = !open" @if($total === 0) disabled @endif
                                    class="w-full flex items-center gap-3 px-5 py-3 text-left transition-colors {{ $total ? 'hover:bg-gray-50' : 'cursor-default' }}">
                                <svg class="w-3.5 h-3.5 transition-transform flex-shrink-0 {{ $total ? 'text-gray-400' : 'text-gray-200' }}" :class="open ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="flex-1 text-sm font-medium {{ $total ? 'text-gray-800' : 'text-gray-400' }}">{{ $swimmer->lastname }}, {{ $swimmer->firstname }}</span>
                                @if($total > 0)
                                    <div class="w-20 bg-gray-200 rounded-full h-1.5 flex-shrink-0">
                                        <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ round($achieved / $total * 100) }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 font-medium w-10 text-right flex-shrink-0">{{ $achieved }}/{{ $total }}</span>
                                @else
                                    <span class="text-xs text-gray-400 flex-shrink-0">keine Ziele</span>
                                @endif
                            </button>

                            @if($total > 0)
                            <div x-show="open" x-cloak class="pb-2">
                                @foreach($swimGoals as $goal)
                                    @php $myComment = $goal->comments->firstWhere('trainer_id', auth()->id()); @endphp
                                    <div class="px-5 pl-11 py-2.5" x-data="{ commentOpen: false }">
                                        <div class="flex items-start gap-3">
                                            <div class="flex-shrink-0 mt-0.5">
                                                @if($goal->achieved)
                                                    <div class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center">
                                                        <svg class="w-3 h-3 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                    </div>
                                                @else
                                                    <div class="w-5 h-5 border-2 border-gray-200 rounded-full"></div>
                                                @endif
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $typeColors[$goal->type] ?? 'bg-gray-100 text-gray-600' }}">{{ $typeLabels[$goal->type] ?? $goal->type }}</span>
                                                    <p class="text-sm {{ $goal->achieved ? 'font-bold text-green-700' : 'font-semibold text-gray-700' }}">{{ $goal->title }}</p>
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
                                                    <div class="flex items-center gap-2 mt-1.5 max-w-xs">
                                                        <div class="flex-1 bg-gray-200 rounded-full h-1.5"><div class="bg-teal-500 h-1.5 rounded-full" style="width: {{ $goal->progress }}%"></div></div>
                                                        <span class="text-xs text-gray-500 font-medium">{{ $goal->progress }} %</span>
                                                    </div>
                                                @endif

                                                @if($goal->notes)
                                                    <p class="text-xs text-gray-400 mt-0.5 italic">{{ $goal->notes }}</p>
                                                @endif

                                                @foreach($goal->comments->where('trainer_id', '!=', auth()->id()) as $comment)
                                                    <div class="mt-2 bg-blue-50 border border-blue-100 rounded-lg px-2.5 py-1.5">
                                                        <p class="text-xs text-blue-600"><span class="font-semibold">{{ $comment->trainer->name }}:</span> {{ $comment->comment }}</p>
                                                    </div>
                                                @endforeach

                                                @if($myComment)
                                                    <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 flex items-start justify-between gap-2">
                                                        <p class="text-xs text-amber-700 leading-relaxed"><span class="font-semibold">Mein Kommentar:</span> {{ $myComment->comment }}</p>
                                                        <button type="button" @click="commentOpen = true" class="text-xs text-amber-600 hover:text-amber-800 whitespace-nowrap font-medium">Bearbeiten</button>
                                                    </div>
                                                @endif
                                            </div>

                                            @if(!$myComment)
                                                <button type="button" @click="commentOpen = !commentOpen"
                                                        class="flex-shrink-0 text-xs px-2.5 py-1.5 border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                                                    + Kommentar
                                                </button>
                                            @endif
                                        </div>

                                        <div x-show="commentOpen" x-cloak class="mt-3 pt-3 border-t border-gray-100">
                                            <form method="POST" action="{{ route('trainer.goals.comment', $goal) }}" class="flex gap-2">
                                                @csrf
                                                <textarea name="comment" rows="2" required maxlength="1000"
                                                          class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/30 outline-none resize-none"
                                                          placeholder="Kommentar eingeben...">{{ $myComment?->comment }}</textarea>
                                                <div class="flex flex-col gap-1.5">
                                                    <button type="submit" class="px-3 py-2 bg-primary text-white rounded-lg text-xs font-semibold hover:bg-primary-700 transition-colors">Speichern</button>
                                                    <button type="button" @click="commentOpen = false" class="px-3 py-2 border border-gray-200 text-gray-500 rounded-lg text-xs hover:bg-gray-50 transition-colors">Abbrechen</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

        </div>
    @endforeach

    @endif
</div>
@endsection

@push('scripts')
<script>
    // Auf-/Zugeklappt-Zustand ueber das Neuladen nach dem Speichern hinweg
    // merken - sonst klappt jede Bewertung die Karte wieder zu.
    // allowed: optionale Liste gueltiger Werte (z.B. Gruppen-IDs) - ein
    // gemerkter Wert, den es nicht mehr gibt, faellt auf fallback zurueck.
    function keep(key, fallback, allowed = null) {
        let initial = fallback;
        try {
            const raw = localStorage.getItem(key);
            if (raw !== null) {
                const v = JSON.parse(raw);
                if (!allowed || allowed.includes(v)) initial = v;
            }
        } catch (e) {}
        return {
            open: initial,
            init() {
                this.$watch('open', v => { try { localStorage.setItem(key, JSON.stringify(v)); } catch (e) {} });
            },
        };
    }
</script>
@endpush
