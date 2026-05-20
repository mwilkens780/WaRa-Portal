@extends('layouts.app')
@section('title', $session->title)
@section('page-title', $session->title)

@section('content')
<div class="mt-2 space-y-6" x-data="{ activeTab: 'attendance' }">

    {{-- Session-Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1">
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
                    @if($session->duration)<p class="text-xs text-gray-400">{{ $session->duration }}</p>@endif
                </div>
                <div>
                    <p class="text-xs text-gray-500">Ort</p>
                    <p class="font-semibold text-gray-800">{{ $session->location }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Typ</p>
                    <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full {{ $session->type_color }}">{{ $session->type_label }}</span>
                    <p class="text-xs text-gray-400 mt-1">{{ $session->trainer->name }}</p>
                </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <a href="{{ route('trainer.sessions.edit', $session) }}"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Bearbeiten
                </a>
                <form method="POST" action="{{ route('trainer.sessions.destroy', $session) }}"
                      onsubmit="return confirm('Einheit löschen?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-3 py-2 border border-red-200 rounded-lg text-sm text-red-600 hover:bg-red-50 transition-colors">
                        Löschen
                    </button>
                </form>
                @if($session->recurrence_group_id)
                    <form method="POST" action="{{ route('trainer.sessions.destroy-group', $session) }}"
                          onsubmit="return confirm('Alle {{ $siblings->count() + 1 }} Einheiten der Wiederholungsgruppe löschen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-3 py-2 border border-red-200 rounded-lg text-sm text-red-700 hover:bg-red-50 transition-colors">
                            Gruppe löschen
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if($session->notes)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 mb-1">Notizen</p>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $session->notes }}</p>
            </div>
        @endif

        {{-- Beteiligung --}}
        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-6">
            <div>
                <p class="text-xs text-gray-500">Beteiligung</p>
                <p class="text-xl font-bold text-primary">{{ $participationPct }} %</p>
                <p class="text-xs text-gray-400">{{ $presentCount }} / {{ $totalSwimmers }} Schwimmer</p>
            </div>
            <div class="flex-1 bg-gray-100 rounded-full h-3 max-w-xs">
                <div class="bg-primary h-3 rounded-full" style="width: {{ $participationPct }}%"></div>
            </div>
        </div>

        {{-- Wiederholungsgruppe --}}
        @if($session->recurrence_group_id && $siblings->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500 mb-2">Weitere Einheiten dieser Wiederholungsgruppe</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($siblings as $sib)
                        <a href="{{ route('trainer.sessions.show', $sib) }}"
                           class="text-xs px-2.5 py-1 rounded-full border border-primary/30 text-primary hover:bg-primary/5 transition-colors">
                            {{ $sib->date->format('d.m.Y') }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Trainingspläne --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Trainingspläne</h2>
        <div class="grid md:grid-cols-2 gap-6">
            {{-- Teamplan --}}
            <div>
                <p class="text-xs font-medium text-gray-500 mb-2">Teamplan</p>
                @if($session->team_plan_path)
                    <div class="flex items-center gap-3 p-3 bg-blue-50 rounded-lg border border-blue-100">
                        <svg class="w-8 h-8 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-blue-800 truncate">Teamplan vorhanden</p>
                            <a href="{{ route('sessions.plan.download', [$session, 'team']) }}"
                               class="text-xs text-blue-600 hover:underline">Herunterladen</a>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-400 mb-2">Noch kein Teamplan hochgeladen.</p>
                @endif
                <form method="POST" action="{{ route('trainer.sessions.plan.team', $session) }}"
                      enctype="multipart/form-data" class="mt-2">
                    @csrf
                    <div class="flex gap-2 items-center">
                        <input type="file" name="team_plan" accept=".pdf,.doc,.docx,.jpg,.png"
                               class="flex-1 text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        <button type="submit"
                                class="text-xs bg-primary text-white px-3 py-1.5 rounded hover:bg-primary-dark transition-colors flex-shrink-0">
                            {{ $session->team_plan_path ? 'Ersetzen' : 'Hochladen' }}
                        </button>
                    </div>
                    @error('team_plan')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </form>
            </div>

            {{-- Individueller Plan --}}
            <div>
                <p class="text-xs font-medium text-gray-500 mb-2">Individueller Plan</p>
                @if($session->individual_plan_path)
                    <div class="flex items-center gap-3 p-3 bg-green-50 rounded-lg border border-green-100">
                        <svg class="w-8 h-8 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-green-800 truncate">Individueller Plan vorhanden</p>
                            <a href="{{ route('sessions.plan.download', [$session, 'individual']) }}"
                               class="text-xs text-green-600 hover:underline">Herunterladen</a>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-400 mb-2">Noch kein individueller Plan hochgeladen.</p>
                @endif
                <form method="POST" action="{{ route('trainer.sessions.plan.individual', $session) }}"
                      enctype="multipart/form-data" class="mt-2">
                    @csrf
                    <div class="flex gap-2 items-center">
                        <input type="file" name="individual_plan" accept=".pdf,.doc,.docx,.jpg,.png"
                               class="flex-1 text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        <button type="submit"
                                class="text-xs bg-green-600 text-white px-3 py-1.5 rounded hover:bg-green-700 transition-colors flex-shrink-0">
                            {{ $session->individual_plan_path ? 'Ersetzen' : 'Hochladen' }}
                        </button>
                    </div>
                    @error('individual_plan')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                </form>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="flex border-b border-gray-100 overflow-x-auto">
            <button @click="activeTab = 'attendance'"
                    :class="activeTab === 'attendance' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap">
                Anwesenheit
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs px-1.5 py-0.5 rounded-full">{{ $swimmers->count() }}</span>
            </button>
            <button @click="activeTab = 'times'"
                    :class="activeTab === 'times' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap">
                Zeiten
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs px-1.5 py-0.5 rounded-full">{{ $session->swimmingTimes->count() }}</span>
            </button>
            <button @click="activeTab = 'diary'"
                    :class="activeTab === 'diary' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="px-5 py-3 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap">
                Tagebuch
                <span class="ml-1.5 bg-gray-100 text-gray-600 text-xs px-1.5 py-0.5 rounded-full">{{ $session->diaries->count() }}</span>
            </button>
        </div>

        {{-- Anwesenheit Tab --}}
        <div x-show="activeTab === 'attendance'" class="p-5">
            <form method="POST" action="{{ route('trainer.sessions.attendance', $session) }}">
                @csrf
                <div class="space-y-2 mb-5">
                    @forelse($swimmers as $swimmer)
                        @php $isPresent = in_array($swimmer->id, $attendedIds); @endphp
                        <div class="flex items-center gap-4 py-2 border-b border-gray-50 last:border-0">
                            <label class="flex items-center gap-3 flex-1 cursor-pointer">
                                <input type="checkbox"
                                       name="attendance[{{ $swimmer->id }}]"
                                       value="1"
                                       {{ $isPresent ? 'checked' : '' }}
                                       class="w-5 h-5 rounded border-gray-300 text-primary">
                                <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold flex-shrink-0">
                                    {{ substr($swimmer->name, 0, 1) }}
                                </div>
                                <span class="text-sm font-medium text-gray-800">{{ $swimmer->name }}</span>
                                @if($swimmer->birth_date)
                                    <span class="text-xs text-gray-400">({{ $swimmer->age }} J.)</span>
                                @endif
                            </label>
                            <input type="text" name="notes[{{ $swimmer->id }}]"
                                   placeholder="Notiz..."
                                   value="{{ $session->attendances->where('user_id', $swimmer->id)->first()?->notes }}"
                                   class="text-sm px-3 py-1.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none w-40 md:w-56">
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 py-4 text-center">Noch keine Schwimmer im System.</p>
                    @endforelse
                </div>
                @if($swimmers->isNotEmpty())
                    <button type="submit"
                            class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                        Anwesenheit speichern
                    </button>
                @endif
            </form>
        </div>

        {{-- Zeiten Tab --}}
        <div x-show="activeTab === 'times'" x-cloak class="p-5">
            <div class="bg-gray-50 rounded-lg p-4 mb-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Neue Zeit eintragen</h3>
                <form method="POST" action="{{ route('trainer.sessions.time', $session) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-xs text-gray-500 mb-1">Schwimmer</label>
                            <select name="user_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="">Wählen...</option>
                                @foreach($swimmers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Disziplin</label>
                            <select name="discipline" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="freistil">Freistil</option>
                                <option value="brust">Brust</option>
                                <option value="ruecken">Rücken</option>
                                <option value="schmetterling">Schmetterling</option>
                                <option value="lagen">Lagen</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Distanz (m)</label>
                            <select name="distance" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                @foreach([25, 50, 100, 200, 400, 800, 1500] as $d)
                                    <option value="{{ $d }}">{{ $d }} m</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2 sm:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Zeit (Min : Sek , 1/100)</label>
                            <div class="flex gap-1 items-center">
                                <input type="number" name="time_minutes" min="0" value="0" placeholder="0"
                                       class="w-14 px-2 py-2 border border-gray-300 rounded text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                                <span class="text-gray-400 font-semibold">:</span>
                                <input type="number" name="time_seconds" min="0" max="59" required value="0"
                                       class="w-14 px-2 py-2 border border-gray-300 rounded text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                                <span class="text-gray-400 font-semibold">,</span>
                                <input type="number" name="time_centiseconds" min="0" max="99" required value="0"
                                       class="w-14 px-2 py-2 border border-gray-300 rounded text-sm text-center focus:ring-2 focus:ring-blue-500 outline-none">
                            </div>
                        </div>
                    </div>
                    <button type="submit"
                            class="bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
                        Zeit speichern
                    </button>
                </form>
            </div>

            @if($session->swimmingTimes->isNotEmpty())
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Schwimmer</th>
                            <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Disziplin</th>
                            <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Distanz</th>
                            <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Zeit</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($session->swimmingTimes->sortBy('user.name') as $time)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5 font-medium text-gray-800">{{ $time->user->name }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $time->discipline_label }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ $time->distance }} m</td>
                                <td class="px-4 py-2.5 font-mono font-semibold text-primary">
                                    {{ $time->formatted_time }}
                                    @if($time->is_personal_best)
                                        <span class="ml-1 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-sans font-medium">PB</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <form method="POST" action="{{ route('trainer.times.destroy', $time) }}"
                                          onsubmit="return confirm('Zeit löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600 text-xs">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-gray-400 py-4 text-center">Noch keine Zeiten für diese Einheit eingetragen.</p>
            @endif
        </div>

        {{-- Tagebuch Tab --}}
        <div x-show="activeTab === 'diary'" x-cloak class="p-5">
            <div class="space-y-4">
                @forelse($session->diaries as $entry)
                    <div class="border border-gray-100 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold">
                                    {{ substr($entry->user->name, 0, 1) }}
                                </div>
                                <span class="text-sm font-semibold text-gray-800">{{ $entry->user->name }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                @if($entry->mood)
                                    <span class="{{ $entry->mood_color }} font-medium">
                                        {{ $entry->mood_emoji }} {{ $entry->mood_label }}
                                    </span>
                                @endif
                                @if($entry->perceived_intensity)
                                    <span class="text-gray-500">Intensität: <strong>{{ $entry->perceived_intensity }}/10</strong></span>
                                @endif
                            </div>
                        </div>
                        @if($entry->body)
                            <p class="text-sm text-gray-600 whitespace-pre-line">{{ $entry->body }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-4 text-center">Noch keine Tagebucheinträge für diese Einheit.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
