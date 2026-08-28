@extends('layouts.app')
@section('title', $session->title)
@section('page-title', $session->title)

@section('content')
<div class="mt-2 space-y-6 max-w-2xl">

    {{-- Session-Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-gray-500">Datum</p>
                <p class="font-semibold text-gray-800">{{ $session->date->format('d.m.Y') }}</p>
                <p class="text-xs text-gray-400">{{ $session->date->isoFormat('dddd') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Uhrzeit</p>
                <p class="font-semibold text-gray-800">{{ $session->start_time }}
                    @if($session->end_time) – {{ $session->end_time }} @endif
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Ort</p>
                <p class="font-semibold text-gray-800">{{ $session->location }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Trainer</p>
                <p class="font-semibold text-gray-800">{{ $session->trainer?->name ?? '–' }}</p>
                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full {{ $session->type_color }} mt-0.5">
                    {{ $session->type_label }}
                </span>
            </div>
        </div>
        @if($session->notes)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 mb-1">Notizen des Trainers</p>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $session->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Vorab-Absage (nur wenn Training noch nicht stattgefunden hat) --}}
    @if($session->date->gt(today()))
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Meine Anmeldung</h2>
            @if($myAttendance?->pre_absent)
                <div class="flex items-center gap-3 mb-3 p-3 bg-red-50 rounded-lg border border-red-100">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-red-700">Du hast für diese Einheit abgesagt.</p>
                        @if($myAttendance->pre_absent_note)
                            <p class="text-xs text-red-500 mt-0.5">Grund: {{ $myAttendance->pre_absent_note }}</p>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('swimmer.session.cancel', $session) }}">
                    @csrf
                    <button type="submit"
                            class="text-sm text-gray-600 border border-gray-200 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                        Absage zurücknehmen
                    </button>
                </form>
            @else
                <p class="text-sm text-gray-500 mb-3">Du bist für diese Einheit angemeldet. Falls du nicht teilnehmen kannst:</p>
                <form method="POST" action="{{ route('swimmer.session.cancel', $session) }}" class="flex items-center gap-2 flex-wrap">
                    @csrf
                    <input type="text" name="note" placeholder="Grund der Absage (optional)"
                           class="flex-1 text-sm px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary outline-none min-w-[200px]">
                    <button type="submit"
                            class="text-sm text-red-600 border border-red-200 px-4 py-2 rounded-lg hover:bg-red-50 transition-colors flex-shrink-0">
                        Absagen
                    </button>
                </form>
            @endif
        </div>
    @endif

    {{-- Trainingsplan: immer anzeigen, Inhalt nur nach Abschluss --}}
    @if($session->trainingPlan)
        @if($session->date->lte(today()))
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Trainingsplan</h2>

                @if($session->trainingPlan->description)
                    <p class="text-sm text-gray-600 mb-4 whitespace-pre-line">{{ $session->trainingPlan->description }}</p>
                @endif

                @if($session->trainingPlan->attachment_path)
                    <a href="{{ route('sessions.plan.attachment.download', $session) }}"
                       class="inline-flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-700 font-medium hover:bg-blue-100 transition-colors mb-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Anhang herunterladen
                    </a>
                @endif

                @foreach($session->trainingPlan->blocks as $block)
                    @php
                        $myTimes = $myBlockTimes[$block->id] ?? [];
                        $iMin = $block->start_interval_seconds ? intdiv($block->start_interval_seconds, 60) : 0;
                        $iSec = $block->start_interval_seconds ? $block->start_interval_seconds % 60 : 0;
                    @endphp
                    <div class="border border-gray-100 rounded-xl overflow-hidden mb-3">
                        <div class="bg-gray-50 px-4 py-2.5 flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Block {{ $loop->iteration }}</span>
                            @if($block->label)
                                <span class="text-sm font-semibold text-gray-700">{{ $block->label }}</span>
                                <span class="text-gray-300">·</span>
                            @endif
                            @if($block->repetitions && $block->distance)
                                <span class="text-sm font-mono font-bold text-gray-800">{{ $block->repetitions }} × {{ $block->distance }} m</span>
                            @endif
                            @if($block->start_interval_seconds)
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Intervall: {{ $iMin }}:{{ str_pad($iSec, 2, '0', STR_PAD_LEFT) }}</span>
                            @endif
                        </div>
                        <div class="p-4 space-y-2.5">
                            @php $hasBadges = !empty($block->disciplines) || !empty($block->materials) || !empty($block->additions); @endphp
                            @if($hasBadges)
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($block->disciplines ?? [] as $disc)
                                        <span class="text-xs bg-primary text-white px-2 py-0.5 rounded-full font-medium">
                                            {{ \App\Models\TrainingPlanBlock::$disciplineLabels[$disc] ?? $disc }}
                                        </span>
                                    @endforeach
                                    @foreach($block->materials ?? [] as $mat)
                                        <span class="text-xs bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full font-medium">{{ $mat }}</span>
                                    @endforeach
                                    @foreach($block->additions ?? [] as $add)
                                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">{{ $add }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if($block->comment)
                                <p class="text-sm text-gray-600 italic border-l-2 border-gray-200 pl-3">{{ $block->comment }}</p>
                            @endif
                            {{-- Eigene Zeiten --}}
                            @if(($block->repetitions ?? 0) > 0 && !empty($myTimes))
                                <div class="mt-2 overflow-x-auto">
                                    <table class="text-xs">
                                        <thead>
                                            <tr class="bg-blue-50">
                                                @for($i = 1; $i <= min($block->repetitions, 50); $i++)
                                                    <th class="px-3 py-1.5 text-center text-gray-500 font-medium min-w-[64px]">{{ $i }}.</th>
                                                @endfor
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                @for($i = 1; $i <= min($block->repetitions, 50); $i++)
                                                    <td class="px-3 py-1.5 text-center font-mono font-semibold text-primary">
                                                        {{ isset($myTimes[$i]) ? \App\Models\TrainingBlockTime::format($myTimes[$i]) : '–' }}
                                                    </td>
                                                @endfor
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <p class="text-sm text-amber-700">Der Trainingsplan wird nach Abschluss der Einheit freigeschaltet.</p>
            </div>
        @endif
    @endif

    {{-- Selbsteinschätzung (nur für vergangene Trainings) --}}
    @if($session->date->lte(today()))
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Meine Selbsteinschätzung</h2>

            @if(session('success'))
                <div class="mb-4 px-4 py-2.5 bg-green-50 border border-green-100 rounded-lg text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('sessions.diary', $session) }}"
                  x-data="scoreKnob({{ $diary?->self_score ?? 5 }})"
                  @submit="document.getElementById('selfScoreInput').value = value">
                @csrf

                <div class="flex flex-col items-center gap-4">
                    {{-- Drehegler --}}
                    <div class="select-none touch-none"
                         @mousedown.prevent="startDrag($event)"
                         @mousemove.window.prevent="onDrag($event)"
                         @mouseup.window="stopDrag()"
                         @touchstart.prevent="startDrag($event)"
                         @touchmove.window.prevent="onDrag($event)"
                         @touchend.window="stopDrag()">
                        <svg viewBox="0 0 120 120" class="w-36 h-36 cursor-grab active:cursor-grabbing" style="filter:drop-shadow(0 2px 8px rgba(0,0,0,.10))">
                            {{-- Hintergrundspur --}}
                            <path :d="trackPath" fill="none" stroke="#e5e7eb" stroke-width="10" stroke-linecap="round"/>
                            {{-- Wertspur --}}
                            <path :d="valuePath" fill="none" :stroke="arcColor" stroke-width="10" stroke-linecap="round"/>
                            {{-- Wert --}}
                            <text x="60" y="55" text-anchor="middle" dominant-baseline="middle"
                                  class="font-bold" style="font-size:28px;font-family:inherit" :fill="arcColor" x-text="value"></text>
                            <text x="60" y="78" text-anchor="middle" style="font-size:11px;fill:#9ca3af;font-family:inherit">von 10</text>
                        </svg>
                    </div>

                    {{-- Tasten --}}
                    <div class="flex items-center gap-4">
                        <button type="button" @click="decrement()"
                                class="w-9 h-9 rounded-full border border-gray-200 text-gray-600 text-lg font-bold hover:bg-gray-50 transition flex items-center justify-center">−</button>
                        <span class="text-xs text-gray-400 w-24 text-center" x-text="label"></span>
                        <button type="button" @click="increment()"
                                class="w-9 h-9 rounded-full border border-gray-200 text-gray-600 text-lg font-bold hover:bg-gray-50 transition flex items-center justify-center">+</button>
                    </div>

                    <input type="hidden" name="self_score" id="selfScoreInput" :value="value">

                    <button type="submit"
                            class="bg-primary hover:bg-primary-dark text-white font-semibold px-8 py-2.5 rounded-lg transition-colors text-sm">
                        {{ $diary ? 'Einschätzung aktualisieren' : 'Einschätzung speichern' }}
                    </button>
                </div>
            </form>

            {{-- Trainereinschätzung (nur lesen) --}}
            @if($diary?->trainer_score !== null)
                <div class="mt-5 pt-5 border-t border-gray-100">
                    <p class="text-xs text-gray-500 mb-2">Trainereinschätzung</p>
                    <div class="flex items-center gap-3">
                        @php $dev = $diary->deviation_level; @endphp
                        <span class="text-2xl font-bold {{ $diary->score_color }}">{{ $diary->trainer_score }}<span class="text-sm font-normal text-gray-400">/10</span></span>
                        @if($dev === 'match')
                            <span class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Übereinstimmung
                            </span>
                        @elseif($dev === 'minor')
                            <span class="inline-flex items-center gap-1 text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">
                                ~ Geringe Abweichung ({{ $diary->deviation }})
                            </span>
                        @elseif($dev === 'major')
                            <span class="inline-flex items-center gap-1 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/></svg>
                                Starke Abweichung ({{ $diary->deviation }})
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-400 text-center py-2">Die Selbsteinschätzung ist nach dem Training verfügbar.</p>
        </div>
    @endif

@push('scripts')
<script>
function scoreKnob(initial) {
    const CX = 60, CY = 60, R = 44;
    const START_DEG = 135, RANGE_DEG = 270;

    function polar(deg) {
        const rad = (deg - 90) * Math.PI / 180;
        return { x: CX + R * Math.cos(rad), y: CY + R * Math.sin(rad) };
    }

    function arcPath(fromDeg, toDeg) {
        const s = polar(fromDeg), e = polar(toDeg);
        const large = (toDeg - fromDeg) > 180 ? 1 : 0;
        return `M ${s.x.toFixed(2)} ${s.y.toFixed(2)} A ${R} ${R} 0 ${large} 1 ${e.x.toFixed(2)} ${e.y.toFixed(2)}`;
    }

    const LABELS = ['Sehr niedrig','Sehr niedrig','Niedrig','Niedrig','Mittel','Mittel','Gut','Gut','Hoch','Sehr hoch','Maximal'];

    return {
        value: Math.min(10, Math.max(0, initial)),
        dragging: false,
        startAngle: null,
        startValue: null,

        get trackPath() { return arcPath(START_DEG, START_DEG + RANGE_DEG); },
        get valuePath() {
            if (this.value === 0) return `M ${polar(START_DEG).x.toFixed(2)} ${polar(START_DEG).y.toFixed(2)}`;
            return arcPath(START_DEG, START_DEG + (this.value / 10) * RANGE_DEG);
        },
        get arcColor() {
            if (this.value >= 8) return '#16a34a';
            if (this.value >= 5) return '#f59e0b';
            return '#ef4444';
        },
        get label() { return LABELS[this.value] || ''; },

        angleFromEvent(e) {
            const rect = this.$el.getBoundingClientRect();
            const touch = e.touches ? e.touches[0] : e;
            const dx = touch.clientX - (rect.left + rect.width / 2);
            const dy = touch.clientY - (rect.top + rect.height / 2);
            return Math.atan2(dy, dx) * 180 / Math.PI;
        },

        startDrag(e) {
            this.dragging = true;
            this.startAngle = this.angleFromEvent(e);
            this.startValue = this.value;
        },
        onDrag(e) {
            if (!this.dragging) return;
            let delta = this.angleFromEvent(e) - this.startAngle;
            if (delta > 180) delta -= 360;
            if (delta < -180) delta += 360;
            const newVal = Math.round(this.startValue + delta / RANGE_DEG * 10);
            this.value = Math.min(10, Math.max(0, newVal));
        },
        stopDrag() { this.dragging = false; },
        increment() { if (this.value < 10) this.value++; },
        decrement() { if (this.value > 0) this.value--; },
    };
}
</script>
@endpush

    <a href="{{ route('swimmer.dashboard') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zum Dashboard
    </a>
</div>
@endsection
