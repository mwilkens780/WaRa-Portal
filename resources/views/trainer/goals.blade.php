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

            {{-- ─── Zielauswertung (5-Stufen Bewertungssystem) ──────────────── --}}
            @php
                $tgGoals = $trainingGroupGoals[$group->id] ?? collect();
                $ratingHex = [
                    5 => '#22c55e', 4 => '#3b82f6', 3 => '#eab308',
                    2 => '#f97316', 1 => '#9ca3af', 0 => '#f3f4f6',
                ];
                $ratingBg = [
                    5 => 'bg-green-100 text-green-700',
                    4 => 'bg-blue-100 text-blue-700',
                    3 => 'bg-yellow-100 text-yellow-700',
                    2 => 'bg-orange-100 text-orange-700',
                    1 => 'bg-gray-100 text-gray-600',
                    0 => 'bg-gray-50 text-gray-400',
                ];
            @endphp
            @if($tgGoals->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-3 bg-indigo-50/60 border-b border-indigo-100">
                    <svg class="w-4 h-4 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <h3 class="font-semibold text-indigo-800 text-sm">Zielauswertung Gruppenziele</h3>
                    <span class="text-xs text-indigo-400 ml-1">· {{ $tgGoals->count() }} {{ $tgGoals->count() === 1 ? 'Ziel' : 'Ziele' }}</span>
                </div>

                <div class="divide-y divide-gray-50">
                @foreach($tgGoals as $goal)
                @php
                    $totalSw      = $group->swimmers->count();
                    $selfEvs      = $goal->evaluations->where('evaluation_type', 'self');
                    $trainerEvs   = $goal->evaluations->where('evaluation_type', 'trainer');
                    $evaluatedCnt = $selfEvs->count();
                    $isQuant      = $goal->type === 'quantitative';

                    $hexMap = [5=>'#22c55e', 4=>'#3b82f6', 3=>'#eab308', 2=>'#f97316', 1=>'#9ca3af', 0=>'#f3f4f6'];

                    // ── Eigenbewertung ──────────────────────────────────────────
                    $gradParts = []; $chartSegs = []; $cum = 0;
                    $avgRating = null; $centerSelf = null; $subtextSelf = null;

                    if ($isQuant) {
                        $erfuelltCnt = $selfEvs->where('rating', 5)->count();
                        $nichtCnt    = $selfEvs->where('rating', 1)->count();
                        $unevalSelf  = max(0, $totalSw - $selfEvs->count());
                        foreach ([[5,'Erfüllt','#22c55e'],   [1,'Nicht erfüllt','#ef4444']] as [$rv,$lv,$cv]) {
                            $cnt = $rv === 5 ? $erfuelltCnt : $nichtCnt;
                            if ($cnt > 0 && $totalSw > 0) {
                                $p = $cnt / $totalSw * 100;
                                $gradParts[] = $cv.' '.round($cum,2).'% '.round($cum+$p,2).'%';
                                $chartSegs[] = ['rating'=>$rv, 'label'=>$lv, 'color'=>$cv, 'count'=>$cnt, 'pct'=>round($p)];
                                $cum += $p;
                            }
                        }
                        if ($unevalSelf > 0 && $totalSw > 0) {
                            $p = $unevalSelf / $totalSw * 100;
                            $gradParts[] = '#f3f4f6 '.round($cum,2).'% '.round($cum+$p,2).'%';
                            $chartSegs[] = ['rating'=>0, 'label'=>'Nicht bewertet', 'color'=>'#e5e7eb', 'count'=>$unevalSelf, 'pct'=>round($p)];
                        }
                        if ($totalSw > 0 && $selfEvs->count() > 0) {
                            $centerSelf  = round($erfuelltCnt / $totalSw * 100).'%';
                            $subtextSelf = $erfuelltCnt.'/'.$totalSw.' erfüllt';
                        }
                    } else {
                        $unevalSelf = max(0, $totalSw - $selfEvs->count());
                        $avgRating  = $selfEvs->count() > 0 ? round($selfEvs->avg('rating'), 1) : null;
                        foreach ([5,4,3,2,1] as $r) {
                            $cnt = $selfEvs->where('rating', $r)->count();
                            if ($cnt > 0 && $totalSw > 0) {
                                $p = $cnt / $totalSw * 100;
                                $gradParts[] = $hexMap[$r].' '.round($cum,2).'% '.round($cum+$p,2).'%';
                                $chartSegs[] = ['rating'=>$r, 'label'=>$r.'★ '.(\App\Models\TrainingGroupGoal::$ratingLabels[$r] ?? ''), 'color'=>$hexMap[$r], 'count'=>$cnt, 'pct'=>round($p)];
                                $cum += $p;
                            }
                        }
                        if ($unevalSelf > 0 && $totalSw > 0) {
                            $p = $unevalSelf / $totalSw * 100;
                            $gradParts[] = $hexMap[0].' '.round($cum,2).'% '.round($cum+$p,2).'%';
                            $chartSegs[] = ['rating'=>0, 'label'=>'Nicht bewertet', 'color'=>$hexMap[0], 'count'=>$unevalSelf, 'pct'=>round($p)];
                        }
                        $centerSelf = $avgRating ? round($avgRating / 5 * 100).'%' : null;
                    }
                    $gradCss = count($gradParts) ? implode(', ', $gradParts) : '#f3f4f6 0% 100%';

                    // ── Trainerbewertung ────────────────────────────────────────
                    $tGradParts = []; $tChartSegs = []; $tCum = 0;
                    $tAvgRating = null; $centerTrainer = null; $subtextTrainer = null;

                    if ($isQuant) {
                        $tErfuelltCnt = $trainerEvs->where('rating', 5)->count();
                        $tNichtCnt    = $trainerEvs->where('rating', 1)->count();
                        $tUnevalSelf  = max(0, $totalSw - $trainerEvs->count());
                        foreach ([[5,'Erfüllt','#22c55e'],   [1,'Nicht erfüllt','#ef4444']] as [$rv,$lv,$cv]) {
                            $cnt = $rv === 5 ? $tErfuelltCnt : $tNichtCnt;
                            if ($cnt > 0 && $totalSw > 0) {
                                $p = $cnt / $totalSw * 100;
                                $tGradParts[] = $cv.' '.round($tCum,2).'% '.round($tCum+$p,2).'%';
                                $tChartSegs[] = ['rating'=>$rv, 'label'=>$lv, 'color'=>$cv, 'count'=>$cnt, 'pct'=>round($p)];
                                $tCum += $p;
                            }
                        }
                        if ($tUnevalSelf > 0 && $totalSw > 0) {
                            $p = $tUnevalSelf / $totalSw * 100;
                            $tGradParts[] = '#f3f4f6 '.round($tCum,2).'% '.round($tCum+$p,2).'%';
                            $tChartSegs[] = ['rating'=>0, 'label'=>'Nicht bewertet', 'color'=>'#e5e7eb', 'count'=>$tUnevalSelf, 'pct'=>round($p)];
                        }
                        if ($totalSw > 0 && $trainerEvs->count() > 0) {
                            $centerTrainer  = round($tErfuelltCnt / $totalSw * 100).'%';
                            $subtextTrainer = $tErfuelltCnt.'/'.$totalSw.' erfüllt';
                        }
                    } else {
                        $tEvalCnt    = $trainerEvs->count();
                        $tUnevalSelf = max(0, $totalSw - $tEvalCnt);
                        $tAvgRating  = $tEvalCnt > 0 ? round($trainerEvs->avg('rating'), 1) : null;
                        foreach ([5,4,3,2,1] as $r) {
                            $cnt = $trainerEvs->where('rating', $r)->count();
                            if ($cnt > 0 && $totalSw > 0) {
                                $p = $cnt / $totalSw * 100;
                                $tGradParts[] = $hexMap[$r].' '.round($tCum,2).'% '.round($tCum+$p,2).'%';
                                $tChartSegs[] = ['rating'=>$r, 'label'=>$r.'★ '.(\App\Models\TrainingGroupGoal::$ratingLabels[$r] ?? ''), 'color'=>$hexMap[$r], 'count'=>$cnt, 'pct'=>round($p)];
                                $tCum += $p;
                            }
                        }
                        if ($tUnevalSelf > 0 && $totalSw > 0) {
                            $p = $tUnevalSelf / $totalSw * 100;
                            $tGradParts[] = $hexMap[0].' '.round($tCum,2).'% '.round($tCum+$p,2).'%';
                            $tChartSegs[] = ['rating'=>0, 'label'=>'Nicht bewertet', 'color'=>$hexMap[0], 'count'=>$tUnevalSelf, 'pct'=>round($p)];
                        }
                        $centerTrainer = $tAvgRating ? round($tAvgRating / 5 * 100).'%' : null;
                    }
                    $tGradCss = count($tGradParts) ? implode(', ', $tGradParts) : '#f3f4f6 0% 100%';
                @endphp
                <div class="p-4" x-data="{ filterSelf: null, filterTrainer: null, evaluating: null, showNotes: false }">

                    {{-- Goal header --}}
                    <div class="flex items-start gap-3 mb-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($goal->type === 'quantitative')
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Messbar</span>
                                @else
                                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Qualitativ</span>
                                @endif
                                <span class="text-sm font-bold text-gray-800">{{ $goal->title }}</span>
                                @if($goal->target_value)
                                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Ziel: {{ $goal->target_value }}</span>
                                @endif
                            </div>
                            @if($goal->description)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $goal->description }}</p>
                            @endif
                        </div>
                        <div class="text-right flex-shrink-0 text-xs text-gray-400">
                            {{ $evaluatedCnt }}/{{ $totalSw }} bewertet
                        </div>
                    </div>

                    {{-- Zwei Donut-Charts nebeneinander --}}
                    @php
                        $avgLabel = function($avg) {
                            if (!$avg) return null;
                            if ($avg >= 4.5) return ['text' => 'Ziel erreicht', 'cls' => 'text-green-600'];
                            if ($avg >= 3.5) return ['text' => 'Gut auf dem Weg', 'cls' => 'text-blue-600'];
                            if ($avg >= 2.5) return ['text' => 'In Arbeit', 'cls' => 'text-yellow-600'];
                            if ($avg >= 1.5) return ['text' => 'Erste Schritte', 'cls' => 'text-orange-500'];
                            return ['text' => 'Noch nicht begonnen', 'cls' => 'text-gray-500'];
                        };
                    @endphp
                    <div class="flex flex-wrap gap-0 divide-x divide-gray-100 -mx-4 px-4 border-t border-gray-50 pt-3 mt-1">

                        {{-- Eigenbewertung --}}
                        <div class="flex-1 min-w-[260px] pr-6 pb-2">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Eigenbewertung</p>
                            <div class="flex items-start gap-3">
                                <div class="relative flex-shrink-0" style="width:76px;height:76px;border-radius:50%;background:conic-gradient({{ $gradCss }})">
                                    <div class="absolute rounded-full bg-white flex flex-col items-center justify-center" style="width:44px;height:44px;top:16px;left:16px;">
                                        @if($centerSelf)
                                            <span class="text-[11px] font-black text-gray-800 leading-none">{{ $centerSelf }}</span>
                                        @else
                                            <span class="text-lg text-gray-200 leading-none">–</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex flex-col gap-1">
                                        @foreach($chartSegs as $seg)
                                        <button type="button"
                                                @click="filterSelf = filterSelf === {{ $seg['rating'] }} ? null : {{ $seg['rating'] }}"
                                                :class="filterSelf === {{ $seg['rating'] }} ? 'ring-1 ring-offset-1 ring-gray-400' : 'opacity-75 hover:opacity-100'"
                                                class="flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-medium border transition-all text-left"
                                                style="border-color:{{ $seg['color'] }};color:{{ $seg['color'] }}">
                                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $seg['color'] }}"></span>
                                            <span class="flex-1">{{ $seg['label'] }}</span>
                                            <span class="font-bold ml-auto">{{ $seg['count'] }}</span>
                                        </button>
                                        @endforeach
                                        <button type="button" x-show="filterSelf !== null" @click="filterSelf = null"
                                                class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] text-gray-400 border border-gray-200 hover:bg-gray-50 transition-colors">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Filter aufheben
                                        </button>
                                    </div>
                                    @if($isQuant && $subtextSelf)
                                        <p class="text-[10px] text-gray-400 mt-1.5">{{ $subtextSelf }}</p>
                                    @elseif(!$isQuant && $avgRating && ($lbl = $avgLabel($avgRating)))
                                        <p class="text-[10px] text-gray-400 mt-1.5">Ø {{ number_format($avgRating, 1) }}/5 · <span class="font-semibold {{ $lbl['cls'] }}">{{ $lbl['text'] }}</span></p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Trainerbewertung --}}
                        <div class="flex-1 min-w-[260px] pl-6 pb-2">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Trainerbewertung</p>
                            <div class="flex items-start gap-3">
                                <div class="relative flex-shrink-0" style="width:76px;height:76px;border-radius:50%;background:conic-gradient({{ $tGradCss }})">
                                    <div class="absolute rounded-full bg-white flex flex-col items-center justify-center" style="width:44px;height:44px;top:16px;left:16px;">
                                        @if($centerTrainer)
                                            <span class="text-[11px] font-black text-gray-800 leading-none">{{ $centerTrainer }}</span>
                                        @else
                                            <span class="text-lg text-gray-200 leading-none">–</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex flex-col gap-1">
                                        @foreach($tChartSegs as $seg)
                                        <button type="button"
                                                @click="filterTrainer = filterTrainer === {{ $seg['rating'] }} ? null : {{ $seg['rating'] }}"
                                                :class="filterTrainer === {{ $seg['rating'] }} ? 'ring-1 ring-offset-1 ring-gray-400' : 'opacity-75 hover:opacity-100'"
                                                class="flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-medium border transition-all text-left"
                                                style="border-color:{{ $seg['color'] }};color:{{ $seg['color'] }}">
                                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $seg['color'] }}"></span>
                                            <span class="flex-1">{{ $seg['label'] }}</span>
                                            <span class="font-bold ml-auto">{{ $seg['count'] }}</span>
                                        </button>
                                        @endforeach
                                        <button type="button" x-show="filterTrainer !== null" @click="filterTrainer = null"
                                                class="flex items-center gap-1 px-2 py-0.5 rounded text-[10px] text-gray-400 border border-gray-200 hover:bg-gray-50 transition-colors">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Filter aufheben
                                        </button>
                                    </div>
                                    @if($isQuant && $subtextTrainer)
                                        <p class="text-[10px] text-gray-400 mt-1.5">{{ $subtextTrainer }}</p>
                                    @elseif(!$isQuant && $tAvgRating && ($tLbl = $avgLabel($tAvgRating)))
                                        <p class="text-[10px] text-gray-400 mt-1.5">Ø {{ number_format($tAvgRating, 1) }}/5 · <span class="font-semibold {{ $tLbl['cls'] }}">{{ $tLbl['text'] }}</span></p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Swimmer table (filter-driven) --}}
                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-100">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 uppercase tracking-wide text-[10px]">
                                    <th class="text-left px-3 py-2 font-semibold">Schwimmer</th>
                                    <th class="text-left px-3 py-2 font-semibold">Eigenbewertung</th>
                                    @if($goal->type === 'quantitative')
                                    <th class="text-left px-3 py-2 font-semibold">Stand</th>
                                    @endif
                                    <th class="text-left px-3 py-2 font-semibold">Trainerbewertung</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($group->swimmers as $swimmer)
                                @php
                                    $se = $selfEvs->firstWhere('user_id', $swimmer->id);
                                    $te = $trainerEvs->firstWhere('user_id', $swimmer->id);
                                    $selfRating    = $se?->rating ?? 0;
                                    $trainerRating = $te?->rating ?? 0;
                                @endphp
                                <tr :class="{ 'hidden': (filterSelf !== null && filterSelf !== {{ $selfRating }}) || (filterTrainer !== null && filterTrainer !== {{ $trainerRating }}) }"
                                    class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-3 py-2 font-medium text-gray-700 whitespace-nowrap">
                                        {{ $swimmer->lastname }}, {{ $swimmer->firstname }}
                                    </td>
                                    <td class="px-3 py-2">
                                        @if($se && $se->rating)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $ratingBg[$se->rating] ?? '' }}">
                                                {{ $se->rating }}★ {{ \App\Models\TrainingGroupGoal::$ratingLabels[$se->rating] ?? '' }}
                                            </span>
                                            @if($se->notes)
                                                <p class="text-gray-400 italic mt-0.5 max-w-[200px] truncate" title="{{ $se->notes }}">{{ $se->notes }}</p>
                                            @endif
                                        @else
                                            <span class="text-gray-300">–</span>
                                        @endif
                                    </td>
                                    @if($goal->type === 'quantitative')
                                    <td class="px-3 py-2 text-gray-500">
                                        @if($se?->current_value)
                                            <span class="bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded font-mono">{{ $se->current_value }}</span>
                                        @else
                                            <span class="text-gray-200">–</span>
                                        @endif
                                    </td>
                                    @endif
                                    <td class="px-3 py-2">
                                        @if($te && $te->rating)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $ratingBg[$te->rating] ?? '' }}">
                                                {{ $te->rating }}★ {{ \App\Models\TrainingGroupGoal::$ratingLabels[$te->rating] ?? '' }}
                                            </span>
                                            @if($te->notes)
                                                <p class="text-gray-400 italic mt-0.5 max-w-[200px] truncate" title="{{ $te->notes }}">{{ $te->notes }}</p>
                                            @endif
                                        @else
                                            <span class="text-gray-300">–</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button"
                                                @click="evaluating = evaluating === {{ $swimmer->id }} ? null : {{ $swimmer->id }}"
                                                :class="evaluating === {{ $swimmer->id }} ? 'bg-indigo-100 text-indigo-700' : 'border border-gray-200 text-gray-500 hover:bg-gray-50'"
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-semibold transition-colors whitespace-nowrap">
                                            Bewerten
                                        </button>
                                    </td>
                                </tr>
                                {{-- Trainer-eval inline form --}}
                                <tr x-show="evaluating === {{ $swimmer->id }}"
                                    :class="{ 'hidden': (filterSelf !== null && filterSelf !== {{ $selfRating }}) || (filterTrainer !== null && filterTrainer !== {{ $trainerRating }}) }"
                                    class="bg-indigo-50/40">
                                    <td colspan="{{ $goal->type === 'quantitative' ? 5 : 4 }}" class="px-3 py-3">
                                        <form method="POST"
                                              action="{{ route('admin.training-groups.goals.trainer-eval', [$group, $goal, $swimmer]) }}"
                                              class="flex flex-wrap items-end gap-2">
                                            @csrf
                                            <div>
                                                <label class="block text-[10px] text-gray-500 mb-1 font-semibold">Einschätzung</label>
                                                <select name="rating" class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-indigo-400 outline-none">
                                                    <option value="">– Auswählen –</option>
                                                    @if($goal->type === 'quantitative')
                                                        <option value="5" {{ $te?->rating == 5 ? 'selected' : '' }}>Erfüllt</option>
                                                        <option value="1" {{ $te?->rating == 1 ? 'selected' : '' }}>Nicht erfüllt</option>
                                                    @else
                                                        @foreach(\App\Models\TrainingGroupGoal::$ratingLabels as $val => $lbl)
                                                            <option value="{{ $val }}" {{ $te?->rating == $val ? 'selected' : '' }}>
                                                                {{ $val }}★ {{ $lbl }}
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </div>
                                            @if($goal->type === 'quantitative')
                                            <div>
                                                <label class="block text-[10px] text-gray-500 mb-1 font-semibold">Aktueller Stand</label>
                                                <input type="text" name="current_value" maxlength="100"
                                                       value="{{ $te?->current_value }}"
                                                       placeholder="z.B. 65%"
                                                       class="px-2 py-1.5 border border-gray-300 rounded text-xs w-24 focus:ring-1 focus:ring-indigo-400 outline-none">
                                            </div>
                                            @endif
                                            <div class="flex-1 min-w-[140px]">
                                                <label class="block text-[10px] text-gray-500 mb-1 font-semibold">Notiz</label>
                                                <input type="text" name="notes" maxlength="1000"
                                                       value="{{ $te?->notes }}"
                                                       placeholder="Trainer-Einschätzung..."
                                                       class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-indigo-400 outline-none">
                                            </div>
                                            <div class="flex gap-1.5">
                                                <button type="submit"
                                                        class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                                                    Speichern
                                                </button>
                                                <button type="button" @click="evaluating = null"
                                                        class="px-2.5 py-1.5 border border-gray-200 text-gray-500 rounded-lg text-xs hover:bg-gray-50 transition-colors">
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

                    {{-- Kommentare / Notizen Tabelle (collapsible) --}}
                    @php
                        $hasNotes = $selfEvs->filter(fn($e) => $e->notes)->isNotEmpty()
                                 || $trainerEvs->filter(fn($e) => $e->notes)->isNotEmpty();
                    @endphp
                    @if($hasNotes)
                    <div class="mt-3">
                        <button type="button" @click="showNotes = !showNotes"
                                class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-3.5 h-3.5 transition-transform" :class="showNotes ? 'rotate-90' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span x-text="showNotes ? 'Kommentare ausblenden' : 'Alle Kommentare anzeigen'"></span>
                        </button>
                        <div x-show="showNotes" x-transition class="mt-2 overflow-x-auto rounded-lg border border-gray-100">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 uppercase tracking-wide text-[10px]">
                                        <th class="text-left px-3 py-2 font-semibold">Schwimmer</th>
                                        <th class="text-left px-3 py-2 font-semibold">Eigenbewertung</th>
                                        <th class="text-left px-3 py-2 font-semibold">Notiz (Schwimmer)</th>
                                        <th class="text-left px-3 py-2 font-semibold">Trainerbewertung</th>
                                        <th class="text-left px-3 py-2 font-semibold">Notiz (Trainer)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach($group->swimmers as $swimmer)
                                    @php
                                        $se2 = $selfEvs->firstWhere('user_id', $swimmer->id);
                                        $te2 = $trainerEvs->firstWhere('user_id', $swimmer->id);
                                    @endphp
                                    @if(($se2 && ($se2->notes || $se2->rating)) || ($te2 && ($te2->notes || $te2->rating)))
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="px-3 py-2 font-medium text-gray-700 whitespace-nowrap">
                                            {{ $swimmer->lastname }}, {{ $swimmer->firstname }}
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($se2?->rating)
                                                <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold {{ $ratingBg[$se2->rating] }} px-1.5 py-0.5 rounded-full">
                                                    {{ $se2->rating }}★
                                                </span>
                                            @else
                                                <span class="text-gray-300">–</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 max-w-[200px]">
                                            @if($se2?->notes)
                                                <span class="italic">{{ $se2->notes }}</span>
                                                @if($se2->current_value)
                                                    <span class="ml-1 bg-blue-50 text-blue-600 px-1 rounded font-mono text-[10px]">{{ $se2->current_value }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-200">–</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($te2?->rating)
                                                <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold {{ $ratingBg[$te2->rating] }} px-1.5 py-0.5 rounded-full">
                                                    {{ $te2->rating }}★
                                                </span>
                                            @else
                                                <span class="text-gray-300">–</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 max-w-[200px]">
                                            @if($te2?->notes)
                                                <span class="italic">{{ $te2->notes }}</span>
                                                @if($te2->current_value)
                                                    <span class="ml-1 bg-blue-50 text-blue-600 px-1 rounded font-mono text-[10px]">{{ $te2->current_value }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-200">–</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                </div>
                @endforeach
                </div>
            </div>
            @endif

            {{-- ─── Individualziele ────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-3 bg-gray-50 border-b border-gray-100">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <h3 class="font-semibold text-gray-700 text-sm">Individualziele</h3>
                    <span class="text-xs text-gray-400">· persönliche Ziele der Schwimmer</span>
                </div>
                @if($group->swimmers->isEmpty())
                    <p class="px-5 py-4 text-sm text-gray-400">Keine aktiven Schwimmer in dieser Gruppe.</p>
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
            </div>{{-- /Individualziele --}}
        </div>{{-- /group panel --}}
    @endforeach

    @endif
</div>
@endsection
