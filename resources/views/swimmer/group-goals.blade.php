@extends('layouts.app')
@section('title', 'Gruppenziele')
@section('page-title', 'Gruppenziele')

@section('content')
<div class="mt-2 space-y-6">

    {{-- Intro --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <p class="text-sm text-gray-600">
            Hier findest du die Ziele deiner eigenen Gruppe sowie die Qualifikationskriterien anderer Gruppen.
            Du kannst deine eigene Einschätzung zu jedem Ziel hinterlegen – sie ist für dich und deinen Trainer sichtbar.
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

    <div class="bg-white rounded-xl shadow-sm border {{ $isMine ? 'border-primary/30' : 'border-gray-100' }} overflow-hidden">
        {{-- Gruppen-Header --}}
        <div class="flex items-center gap-3 px-5 py-3.5 {{ $isMine ? 'bg-primary/5 border-b border-primary/20' : 'bg-gray-50 border-b border-gray-100' }}">
            <span class="w-3 h-3 rounded-full {{ $colors['dot'] }} flex-shrink-0"></span>
            <h2 class="text-sm font-bold text-gray-800">{{ $group->name }}</h2>
            @if($isMine)
                <span class="text-xs bg-primary text-white px-2 py-0.5 rounded-full font-medium">Meine Gruppe</span>
            @else
                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">Qualifikationskriterien</span>
            @endif
            <span class="text-xs text-gray-400 ml-auto">{{ $group->goals->count() }} {{ $group->goals->count() === 1 ? 'Ziel' : 'Ziele' }}</span>
        </div>

        {{-- Ziele --}}
        <div class="divide-y divide-gray-50">
            @foreach($group->goals as $goal)
            @php
                $selfEval   = $selfEvals[$goal->id] ?? null;
                $trainerEval = $trainerEvals[$goal->id] ?? null;
            @endphp
            <div class="p-4" x-data="{ showForm: false }">
                {{-- Ziel-Info --}}
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($goal->type === 'quantitative')
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Messbar</span>
                            @else
                                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Qualitativ</span>
                            @endif
                            <span class="text-sm font-semibold text-gray-800">{{ $goal->title }}</span>
                            @if($goal->target_value)
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full flex-shrink-0">Ziel: {{ $goal->target_value }}</span>
                            @endif
                        </div>
                        @if($goal->description)
                            <p class="text-xs text-gray-500 mt-1">{{ $goal->description }}</p>
                        @endif
                    </div>

                    {{-- Bewertungs-Status --}}
                    @if($selfEval)
                        <div class="flex-shrink-0 text-right">
                            <div class="text-xs {{ $selfEval->rating_color }} font-semibold">
                                {{ str_repeat('★', $selfEval->rating) }}{{ str_repeat('☆', 5 - $selfEval->rating) }}
                            </div>
                            <div class="text-[10px] text-gray-400">{{ $selfEval->rating_label }}</div>
                        </div>
                    @endif
                </div>

                {{-- Aktuelle Bewertungen (zusammenfassung) --}}
                <div class="mt-2 flex flex-wrap gap-3 text-xs">
                    @if($selfEval)
                        <div class="flex items-center gap-1.5">
                            <span class="text-gray-400">Deine Einschätzung:</span>
                            <span class="{{ $selfEval->rating_color }} font-semibold">{{ $selfEval->rating_label }}</span>
                            @if($selfEval->current_value)
                                <span class="text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ $selfEval->current_value }}</span>
                            @endif
                            @if($selfEval->notes)
                                <span class="text-gray-400 italic">"{{ Str::limit($selfEval->notes, 60) }}"</span>
                            @endif
                            <span class="text-gray-300">· {{ $selfEval->evaluated_at->format('d.m.Y') }}</span>
                        </div>
                    @else
                        <span class="text-gray-300">Noch keine Eigenbewertung</span>
                    @endif

                    @if($trainerEval)
                        <div class="flex items-center gap-1.5 border-l border-gray-200 pl-3">
                            <span class="text-gray-400">Trainer:</span>
                            <span class="{{ $trainerEval->rating_color }} font-semibold">{{ $trainerEval->rating_label }}</span>
                            @if($trainerEval->current_value)
                                <span class="text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">{{ $trainerEval->current_value }}</span>
                            @endif
                            @if($trainerEval->notes)
                                <span class="text-gray-500 border-l border-gray-200 pl-2 italic">"{{ Str::limit($trainerEval->notes, 60) }}"</span>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Eigenbewertung abgeben (nur für eigene oder andere sichtbare Gruppen) --}}
                <div class="mt-2">
                    <button @click="showForm = !showForm" type="button"
                            class="text-xs text-primary hover:underline"
                            x-text="showForm ? 'Abbrechen' : '{{ $selfEval ? 'Eigenbewertung aktualisieren' : 'Eigenbewertung abgeben' }}'">
                    </button>

                    <div x-show="showForm" x-transition class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <form method="POST" action="{{ route('swimmer.group-goal.self-eval', $goal) }}"
                              class="flex flex-wrap gap-2 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Einschätzung</label>
                                <select name="rating" class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-blue-400 outline-none">
                                    <option value="">– Auswählen –</option>
                                    @if($goal->type === 'quantitative')
                                        <option value="5" {{ $selfEval?->rating == 5 ? 'selected' : '' }}>Erfüllt</option>
                                        <option value="1" {{ $selfEval?->rating == 1 ? 'selected' : '' }}>Nicht erfüllt</option>
                                    @else
                                        @foreach(\App\Models\TrainingGroupGoal::$ratingLabels as $val => $label)
                                            <option value="{{ $val }}" {{ $selfEval?->rating == $val ? 'selected' : '' }}>
                                                {{ $val }}★ {{ $label }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            @if($goal->type === 'quantitative')
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Aktueller Stand</label>
                                <input type="text" name="current_value" maxlength="100"
                                       value="{{ $selfEval?->current_value }}"
                                       placeholder="{{ $goal->target_value ? 'z.B. 65%' : 'aktueller Wert' }}"
                                       class="px-2 py-1.5 border border-gray-300 rounded text-xs w-28 focus:ring-1 focus:ring-blue-400 outline-none">
                            </div>
                            @endif
                            <div class="flex-1 min-w-[160px]">
                                <label class="block text-xs text-gray-500 mb-1">Notiz <span class="text-gray-300">(optional)</span></label>
                                <input type="text" name="notes" maxlength="1000"
                                       value="{{ $selfEval?->notes }}"
                                       placeholder="Meine Einschätzung dazu..."
                                       class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:ring-1 focus:ring-blue-400 outline-none">
                            </div>
                            <button type="submit"
                                    class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                                Speichern
                            </button>
                        </form>
                    </div>
                </div>

            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    @if($allGroups->every(fn($g) => $g->goals->isEmpty()))
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Noch keine Gruppenziele definiert.
        </div>
    @endif

</div>
@endsection
