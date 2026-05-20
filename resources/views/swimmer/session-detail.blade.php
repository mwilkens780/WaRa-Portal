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
                <p class="font-semibold text-gray-800">{{ $session->trainer->name }}</p>
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

    {{-- Trainingspläne --}}
    @if($session->team_plan_path || $session->individual_plan_path)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Trainingspläne</h2>
        <div class="flex flex-wrap gap-3">
            @if($session->team_plan_path)
                <a href="{{ route('sessions.plan.download', [$session, 'team']) }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-700 font-medium hover:bg-blue-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Teamplan herunterladen
                </a>
            @endif
            @if($session->individual_plan_path)
                <a href="{{ route('sessions.plan.download', [$session, 'individual']) }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-50 border border-green-100 rounded-lg text-sm text-green-700 font-medium hover:bg-green-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Individuellen Plan herunterladen
                </a>
            @endif
        </div>
    </div>
    @endif

    {{-- Tagebucheintrag --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Mein Tagebucheintrag</h2>

        <form method="POST" action="{{ route('sessions.diary', $session) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stimmung</label>
                <div class="flex flex-wrap gap-2">
                    @foreach([
                        'sehr_gut'      => ['label' => 'Sehr gut',    'emoji' => '😄'],
                        'gut'           => ['label' => 'Gut',         'emoji' => '🙂'],
                        'mittel'        => ['label' => 'Mittel',      'emoji' => '😐'],
                        'schlecht'      => ['label' => 'Schlecht',    'emoji' => '😕'],
                        'sehr_schlecht' => ['label' => 'Sehr schlecht', 'emoji' => '😞'],
                    ] as $val => $item)
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="mood" value="{{ $val }}"
                                   {{ old('mood', $diary?->mood) === $val ? 'checked' : '' }}
                                   class="text-primary">
                            <span class="text-sm">{{ $item['emoji'] }} {{ $item['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Wahrgenommene Intensität: <span id="intensityVal">{{ old('perceived_intensity', $diary?->perceived_intensity ?? 5) }}</span>/10
                </label>
                <input type="range" name="perceived_intensity" min="1" max="10"
                       value="{{ old('perceived_intensity', $diary?->perceived_intensity ?? 5) }}"
                       oninput="document.getElementById('intensityVal').textContent = this.value"
                       class="w-full accent-primary">
                <div class="flex justify-between text-xs text-gray-400 mt-1">
                    <span>Leicht</span><span>Mittel</span><span>Sehr intensiv</span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                <textarea name="body" rows="4"
                          class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none text-sm"
                          placeholder="Wie war das Training für dich? Was hat gut geklappt, was möchtest du verbessern?">{{ old('body', $diary?->body) }}</textarea>
                @error('body')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                {{ $diary ? 'Eintrag aktualisieren' : 'Eintrag speichern' }}
            </button>
        </form>
    </div>

    <a href="{{ route('swimmer.dashboard') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zum Dashboard
    </a>
</div>
@endsection
