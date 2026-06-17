@extends('layouts.app')
@section('title', 'Trainings – ' . $child->name)
@section('page-title', 'Trainings – ' . $child->name)

@section('content')
<div class="mt-2 space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('parent.dashboard') }}" class="text-sm text-gray-500 hover:text-primary">← Übersicht</a>
        <span class="text-gray-300">|</span>
        <h1 class="text-sm font-semibold text-gray-700">Bevorstehende Trainings für {{ $child->name }}</h1>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl px-4 py-3">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if($upcoming->isEmpty())
            <p class="text-sm text-gray-400 text-center py-10">Keine bevorstehenden Trainingseinheiten.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($upcoming as $session)
                    @php
                        $absence      = $preAbsenceMap->get($session->id);
                        $isAbsent     = $absence !== null;
                        $isRegistered = $myRegistrations->contains($session->id);
                        $regOpen      = $session->registration_open;
                        $spots        = $session->remainingSpots();
                        $noSpots      = $regOpen && $spots !== null && $spots <= 0 && !$isRegistered;
                    @endphp
                    <div x-data="{ showNote: false }" class="px-4 py-3 {{ $isAbsent ? 'bg-red-50/40' : '' }}">
                        <div class="flex items-start gap-3">

                            {{-- Date block --}}
                            <div class="text-center rounded-lg p-2 min-w-[52px] flex-shrink-0 {{ $isAbsent ? 'bg-red-100' : 'bg-primary/10' }}">
                                <p class="text-xs font-bold {{ $isAbsent ? 'text-red-600' : 'text-primary' }}">{{ $session->date->format('d.M') }}</p>
                                <p class="text-[10px] {{ $isAbsent ? 'text-red-400' : 'text-primary/60' }}">{{ $session->date->isoFormat('ddd') }}</p>
                            </div>

                            {{-- Session info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                    <p class="text-sm font-medium text-gray-800">{{ $session->title }}</p>
                                    <span class="text-xs px-1.5 py-0.5 rounded-full {{ $session->type_color }}">{{ $session->type_label }}</span>
                                    @if($isAbsent)
                                        <span class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full font-semibold">Abgesagt</span>
                                    @endif
                                    @if($regOpen)
                                        <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-semibold">
                                            Anmeldung offen{{ $spots !== null ? ' · '.$spots.' Plätze frei' : '' }}
                                        </span>
                                    @endif
                                    @if($isRegistered)
                                        <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-semibold">Angemeldet</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500">
                                    @if($session->start_time){{ substr($session->start_time,0,5) }}@if($session->end_time) – {{ substr($session->end_time,0,5) }}@endif Uhr · @endif
                                    {{ $session->location }} · {{ $session->trainer?->name ?? '–' }}
                                </p>
                                @if($isAbsent && $absence->pre_absent_note)
                                    <p class="text-xs text-red-500 mt-0.5">Grund: {{ $absence->pre_absent_note }}</p>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="flex-shrink-0 flex flex-col items-end gap-1.5">
                                @if($regOpen && !$isAbsent)
                                    @if($isRegistered)
                                        <form method="POST" action="{{ route('parent.child.session.unregister', [$child->id, $session]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-blue-600 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors">
                                                Abmelden
                                            </button>
                                        </form>
                                    @elseif(!$noSpots)
                                        <form method="POST" action="{{ route('parent.child.session.register', [$child->id, $session]) }}">
                                            @csrf
                                            <button type="submit" class="text-xs text-white bg-green-600 hover:bg-green-700 px-3 py-1.5 rounded-lg transition-colors font-semibold">
                                                Anmelden
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400 border border-gray-200 px-3 py-1.5 rounded-lg">Ausgebucht</span>
                                    @endif
                                @endif

                                @if($isAbsent)
                                    <form method="POST" action="{{ route('parent.child.session.cancel', [$child->id, $session]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-gray-500 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors">
                                            Zurücknehmen
                                        </button>
                                    </form>
                                @else
                                    <button type="button" @click="showNote = !showNote"
                                            class="text-xs text-red-500 border border-red-200 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors"
                                            x-text="showNote ? 'Abbrechen' : 'Absagen'">Absagen</button>
                                @endif
                            </div>
                        </div>

                        @if(!$isAbsent)
                            <div x-show="showNote" x-cloak class="mt-3 ml-[64px]">
                                <form method="POST" action="{{ route('parent.child.session.cancel', [$child->id, $session]) }}"
                                      class="flex items-center gap-2 flex-wrap">
                                    @csrf
                                    <input type="text" name="note" placeholder="Grund der Absage (optional)"
                                           class="flex-1 text-sm px-3 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary outline-none min-w-[160px]">
                                    <button type="submit"
                                            class="text-sm text-red-600 border border-red-200 px-4 py-1.5 rounded-lg hover:bg-red-50 transition-colors flex-shrink-0 font-medium">
                                        Absage bestätigen
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
