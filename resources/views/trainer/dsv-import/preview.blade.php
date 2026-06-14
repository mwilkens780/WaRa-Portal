@extends('layouts.app')
@section('title', 'Import-Vorschau')
@section('page-title', 'Import-Vorschau')

@section('content')
<div class="mt-2 space-y-6"
     x-data="{ meetIndex: 0 }">

    <form method="POST" action="{{ route('trainer.dsv-import.execute') }}" class="space-y-6">
        @csrf

        {{-- Meet-Auswahl (bei mehreren Wettkämpfen in der Datei) --}}
        @if(count($parsed['meets']) > 1)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Mehrere Wettkämpfe in der Datei – bitte einen auswählen:
                </label>
                <select name="meet_index" x-model.number="meetIndex"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                    @foreach($parsed['meets'] as $i => $meet)
                        <option value="{{ $i }}">{{ $meet['name'] }} ({{ $meet['startdate'] }}, {{ $meet['city'] }})</option>
                    @endforeach
                </select>
            </div>
        @else
            <input type="hidden" name="meet_index" value="0">
        @endif

        @foreach($parsed['meets'] as $mi => $meet)
        <div x-show="meetIndex === {{ $mi }}" @if($mi > 0) x-cloak @endif>

            {{-- Wettkampf-Details --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="font-semibold text-gray-800 mb-4">Wettkampf-Details</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="comp_name" required
                               value="{{ old('comp_name', $meet['name']) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        @error('comp_name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Ort <span class="text-red-500">*</span></label>
                        <input type="text" name="comp_location" required
                               value="{{ old('comp_location', $meet['city']) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        @error('comp_location')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Kategorie <span class="text-red-500">*</span></label>
                        <select name="comp_type" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                            @foreach(\App\Models\Competition::TYPE_LABELS as $v => $l)
                                <option value="{{ $v }}" {{ old('comp_type', 'regional') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Startdatum <span class="text-red-500">*</span></label>
                        <input type="date" name="comp_date" required
                               value="{{ old('comp_date', $meet['startdate']) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        @error('comp_date')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Enddatum</label>
                        <input type="date" name="comp_date_end"
                               value="{{ old('comp_date_end', $meet['enddate'] !== $meet['startdate'] ? $meet['enddate'] : '') }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Bahnlänge <span class="text-red-500">*</span></label>
                        <select name="comp_course"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                            <option value="Kurzbahn" {{ ($meet['course'] ?? 'Kurzbahn') === 'Kurzbahn' ? 'selected' : '' }}>Kurzbahn (25 m)</option>
                            <option value="Langbahn" {{ ($meet['course'] ?? '') === 'Langbahn' ? 'selected' : '' }}>Langbahn (50 m)</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Athleten-Zuordnung --}}
            @foreach($meet['clubs'] as $ci => $club)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
                        <div>
                            <h3 class="font-semibold text-gray-800">{{ $club['name'] }}</h3>
                            @if($club['shortname'])
                                <p class="text-xs text-gray-400">{{ $club['shortname'] }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            @php
                                $matched  = collect($club['athletes'])->filter(fn($a) => $a['matched_user_id'])->count();
                                $total    = count($club['athletes']);
                                $results  = collect($club['athletes'])->sum(fn($a) => count($a['results']));
                            @endphp
                            <p class="text-sm font-semibold text-gray-700">{{ $matched }}/{{ $total }} zugeordnet</p>
                            <p class="text-xs text-gray-400">{{ $results }} Ergebnisse gesamt</p>
                        </div>
                    </div>

                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/50 border-b border-gray-100">
                            <tr>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Athlet in Datei</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Ergebnisse</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500">Zuordnung im Portal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($club['athletes'] as $ai => $athlete)
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-gray-800">
                                            {{ $athlete['firstname'] }}
                                            <span class="font-semibold">{{ $athlete['lastname'] }}</span>
                                        </p>
                                        @if($athlete['birthdate'])
                                            <p class="text-xs text-gray-400 mt-0.5">
                                                Jg. {{ $athlete['birthdate'] }}
                                                @if($athlete['gender'] === 'F') · w @elseif($athlete['gender'] === 'M') · m @endif
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="space-y-1">
                                            @foreach($athlete['results'] as $r)
                                                <div class="text-xs text-gray-500">
                                                    <span>{{ $r['distance'] }} m {{ $r['discipline'] }}</span>
                                                    @if($r['round_type'] ?? '') <span class="text-gray-400">{{ ['V'=>'VL','F'=>'Fin','E'=>'E','Z'=>'ZL'][$r['round_type']] ?? $r['round_type'] }}</span> @endif
                                                    <span class="font-mono text-primary font-medium">{{ $r['swimtime'] }}</span>
                                                    @if($r['place'] ?? null) <span class="text-gray-400">Pl.&nbsp;{{ $r['place'] }}</span> @endif
                                                    @if(!empty($r['wertungen']))
                                                        <span class="ml-1 inline-flex flex-wrap gap-0.5">
                                                            @foreach($r['wertungen'] as $w)
                                                                <span class="px-1 py-0.5 bg-indigo-50 text-indigo-600 rounded text-xs">{{ $w }}</span>
                                                            @endforeach
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if($athlete['matched_user_id'])
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 bg-green-400 rounded-full flex-shrink-0"></span>
                                                <select name="mappings[{{ $ci }}][{{ $ai }}]"
                                                        class="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                                    <option value="0">– überspringen –</option>
                                                    @foreach($swimmers as $sw)
                                                        <option value="{{ $sw->id }}"
                                                                {{ $athlete['matched_user_id'] === $sw->id ? 'selected' : '' }}>
                                                            {{ $sw->firstname }} {{ $sw->lastname }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <p class="text-xs text-green-600 mt-0.5 ml-4">Automatisch erkannt</p>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 bg-amber-400 rounded-full flex-shrink-0"></span>
                                                <select name="mappings[{{ $ci }}][{{ $ai }}]"
                                                        class="flex-1 px-3 py-1.5 border border-amber-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-amber-50">
                                                    <option value="0">– überspringen –</option>
                                                    @foreach($swimmers as $sw)
                                                        <option value="{{ $sw->id }}">{{ $sw->firstname }} {{ $sw->lastname }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <p class="text-xs text-amber-600 mt-0.5 ml-4">Nicht automatisch erkannt</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

        </div>
        @endforeach

        {{-- Aktions-Leiste --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-wrap items-center justify-between gap-4">
            <div class="text-sm text-gray-500">
                @php
                    $totalAthletes = collect($parsed['meets'][0]['clubs'] ?? [])->sum(fn($c) => count($c['athletes']));
                    $autoMatched   = collect($parsed['meets'][0]['clubs'] ?? [])->sum(fn($c) =>
                        collect($c['athletes'])->filter(fn($a) => $a['matched_user_id'])->count()
                    );
                    $totalResults  = collect($parsed['meets'][0]['clubs'] ?? [])->sum(fn($c) =>
                        collect($c['athletes'])->sum(fn($a) => count($a['results']))
                    );
                @endphp
                <strong>{{ $autoMatched }}/{{ $totalAthletes }}</strong> Athleten automatisch zugeordnet ·
                <strong>{{ $totalResults }}</strong> Ergebnisse bereit
            </div>
            <div class="flex gap-3">
                <a href="{{ route('trainer.dsv-import.index') }}"
                   class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </a>
                <button type="submit"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Import jetzt ausführen
                </button>
            </div>
        </div>

    </form>
</div>
@endsection
