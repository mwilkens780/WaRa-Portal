@extends('layouts.app')
@section('title', 'Leistungskriterien')
@section('page-title', 'Leistungskriterien')

@php
    $statusLabels = \App\Models\TrainingGroupGoal::$statusLabels;
    $statusBadges = \App\Models\TrainingGroupGoal::$statusBadges;
@endphp

@section('content')
<div class="mt-2 space-y-6">

    {{-- Intro --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-600">
            Leistungskriterien legen fest, was für eine Trainingsgruppe erfüllt sein muss. Hier siehst du
            die Kriterien deiner eigenen Gruppe und die der anderen Gruppen. Du kannst zu jedem Kriterium
            angeben, ob du es erreicht hast – das sehen du und deine Trainer.
        </p>
        <p class="text-xs text-gray-400 mt-2">
            Die Kriterien müssen jede Saison neu erfüllt werden.
            @if($season) Angezeigt wird die {{ $season->label }}. @endif
        </p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    @foreach($allGroups as $group)
    @php
        $isMine = in_array($group->id, $myGroupIds);
        $colors = $group->colorDots;
    @endphp

    @if($group->goals->isEmpty()) @continue @endif

    <div class="bg-white rounded-xl shadow-sm border {{ $isMine ? 'border-primary/30' : 'border-gray-100' }} overflow-hidden"
         x-data="{ open: {{ $isMine ? 'true' : 'false' }} }">
        {{-- Gruppen-Header --}}
        <button type="button" @click="open = !open"
                class="w-full flex items-center gap-3 px-5 py-3.5 text-left {{ $isMine ? 'bg-primary/5' : 'bg-gray-50' }}">
            <svg class="w-3.5 h-3.5 text-gray-400 transition-transform flex-shrink-0" :class="open ? 'rotate-90' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="w-3 h-3 rounded-full {{ $colors['dot'] }} flex-shrink-0"></span>
            <h2 class="text-sm font-bold text-gray-800">{{ $group->name }}</h2>
            @if($isMine)
                <span class="text-xs bg-primary text-white px-2 py-0.5 rounded-full font-medium">Meine Gruppe</span>
            @endif
            <span class="text-xs text-gray-400 ml-auto">{{ $group->goals->count() }} {{ $group->goals->count() === 1 ? 'Kriterium' : 'Kriterien' }}</span>
        </button>

        <div x-show="open" x-cloak class="divide-y divide-gray-50 border-t {{ $isMine ? 'border-primary/20' : 'border-gray-100' }}">
            @foreach($group->goals as $goal)
            @php
                $selfEval    = $selfEvals[$goal->id] ?? null;
                $trainerEval = $trainerEvals[$goal->id] ?? null;
                $selfStatus  = $selfEval?->status ?? 'open';
            @endphp
            <div class="p-4" x-data="{ showForm: false }">
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-gray-800">{{ $goal->title }}</span>
                            @if($goal->target_value)
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full flex-shrink-0">Ziel: {{ $goal->target_value }}</span>
                            @endif
                        </div>
                        @if($goal->description)
                            <p class="text-xs text-gray-500 mt-1">{{ $goal->description }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="text-gray-400">Deine Einschätzung:</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusBadges[$selfStatus] }}">{{ $statusLabels[$selfStatus] }}</span>
                        @if($selfEval?->notes)
                            <span class="text-gray-400 italic">„{{ Str::limit($selfEval->notes, 60) }}“</span>
                        @endif
                    </div>

                    @if($trainerEval && ($trainerEval->achieved !== null || $trainerEval->notes))
                        @php $tStatus = $trainerEval->status; @endphp
                        <div class="flex items-center gap-1.5 border-l border-gray-200 pl-3">
                            <span class="text-gray-400">Trainer:</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $statusBadges[$tStatus] }}">{{ $statusLabels[$tStatus] }}</span>
                            @if($trainerEval->notes)
                                <span class="text-gray-500 italic">„{{ Str::limit($trainerEval->notes, 80) }}“</span>
                            @endif
                        </div>
                    @endif
                </div>

                @if($season)
                <div class="mt-2">
                    <button @click="showForm = !showForm" type="button"
                            class="text-xs text-primary hover:underline"
                            x-text="showForm ? 'Abbrechen' : '{{ $selfEval ? 'Einschätzung ändern' : 'Einschätzung abgeben' }}'">
                    </button>

                    <div x-show="showForm" x-cloak x-transition class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <form method="POST" action="{{ route('swimmer.group-goal.self-eval', $goal) }}"
                              class="flex flex-wrap items-center gap-3">
                            @csrf
                            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs font-semibold">
                                @foreach(['1' => ['Erreicht', 'peer-checked:bg-green-600'], '0' => ['Nicht erreicht', 'peer-checked:bg-red-600'], '' => ['Offen', 'peer-checked:bg-gray-500']] as $val => [$lbl, $on])
                                    <label class="cursor-pointer">
                                        <input type="radio" name="achieved" value="{{ $val }}" class="peer sr-only"
                                               {{ ($val === '1' && $selfStatus === 'achieved') || ($val === '0' && $selfStatus === 'missed') || ($val === '' && $selfStatus === 'open') ? 'checked' : '' }}>
                                        <span class="block px-3 py-1.5 bg-white text-gray-600 {{ $on }} peer-checked:text-white transition-colors">{{ $lbl }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <input type="text" name="notes" maxlength="1000"
                                   value="{{ $selfEval?->notes }}"
                                   placeholder="Notiz (optional)"
                                   class="flex-1 min-w-[160px] px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-blue-400 outline-none">
                            <button type="submit"
                                    class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                                Speichern
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    @if($allGroups->every(fn($g) => $g->goals->isEmpty()))
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Noch keine Leistungskriterien festgelegt.
        </div>
    @endif

</div>
@endsection
