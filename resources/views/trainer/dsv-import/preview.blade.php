@extends('layouts.app')
@section('title', 'Ergebnisse importieren – Vorschau')
@section('page-title', 'Ergebnisse importieren – Vorschau')

@section('content')
<div class="mt-2 space-y-6"
     x-data="{ meetIndex: 0 }">

    <x-ui.import-steps :current="2" />

    <form method="POST" action="{{ route('trainer.dsv-import.execute') }}" class="space-y-6">
        @csrf

        {{-- Meet-Auswahl (bei mehreren Wettkämpfen in der Datei) --}}
        @if(count($parsed['meets']) > 1)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label for="dsv-meet" class="block text-sm font-medium text-gray-700 mb-2">
                    Mehrere Wettkämpfe in der Datei – bitte einen auswählen:
                </label>
                <select id="dsv-meet" name="meet_index" x-model.number="meetIndex"
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
        {{-- Gesperrtes fieldset: Felder nicht gewaehlter Wettkaempfe werden nicht
             gesendet. Vorher gingen alle mit - bei gleichen Namen gewann der letzte. --}}
        <fieldset x-show="meetIndex === {{ $mi }}" :disabled="meetIndex !== {{ $mi }}" @if($mi > 0) x-cloak disabled @endif class="space-y-6 min-w-0">
            <legend class="sr-only">{{ $meet['name'] }}</legend>

            @if(($possibleDuplicates[$mi] ?? collect())->isNotEmpty())
                <div role="alert" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Gibt es diesen Wettkampf schon?</p>
                    <p class="mt-1">Dieser Import legt einen <strong>neuen</strong> Wettkampf an. Im selben Zeitraum ist bereits vorhanden:</p>
                    <ul class="mt-2 space-y-1">
                        @foreach($possibleDuplicates[$mi] as $dup)
                            <li>
                                <a href="{{ route('admin.competitions.show', $dup) }}" class="font-medium underline hover:no-underline">{{ $dup->name }}</a>
                                <span class="text-amber-800">· {{ $dup->date->format('d.m.Y') }}{{ $dup->location ? ' · ' . $dup->location : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2">Wenn es derselbe ist: dort unter „Import“ die Datei einlesen, statt hier fortzufahren.</p>
                </div>
            @endif

            {{-- Wettkampf-Details --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="font-semibold text-gray-800 mb-4">Wettkampf-Details</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1" for="dsv-name-{{ $mi }}">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="comp_name" id="dsv-name-{{ $mi }}" required
                               value="{{ old('comp_name', $meet['name']) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        @error('comp_name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1" for="dsv-location-{{ $mi }}">Ort <span class="text-red-500">*</span></label>
                        <input type="text" name="comp_location" id="dsv-location-{{ $mi }}" required
                               value="{{ old('comp_location', $meet['city']) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        @error('comp_location')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1" for="dsv-type-{{ $mi }}">Kategorie <span class="text-red-500">*</span></label>
                        <select name="comp_type" id="dsv-type-{{ $mi }}" required
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                            @foreach(\App\Models\Competition::TYPE_LABELS as $v => $l)
                                <option value="{{ $v }}" {{ old('comp_type', 'regional') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1" for="dsv-date-{{ $mi }}">Startdatum <span class="text-red-500">*</span></label>
                        <input type="date" name="comp_date" id="dsv-date-{{ $mi }}" required
                               value="{{ old('comp_date', $meet['startdate']) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                        @error('comp_date')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1" for="dsv-date-end-{{ $mi }}">Enddatum</label>
                        <input type="date" name="comp_date_end" id="dsv-date-end-{{ $mi }}"
                               value="{{ old('comp_date_end', $meet['enddate'] !== $meet['startdate'] ? $meet['enddate'] : '') }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1" for="dsv-course-{{ $mi }}">Bahnlänge <span class="text-red-500">*</span></label>
                        <select name="comp_course" id="dsv-course-{{ $mi }}"
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
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600">Athlet in Datei</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600">Ergebnisse</th>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600">Zuordnung im Portal</th>
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
                                                <select name="mappings[{{ $ci }}][{{ $ai }}]" aria-label="Portal-Schwimmer für {{ $athlete['firstname'] }} {{ $athlete['lastname'] }}"
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
                                            <p class="text-xs text-green-700 mt-0.5 ml-4">Automatisch erkannt</p>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 bg-amber-400 rounded-full flex-shrink-0"></span>
                                                <select name="mappings[{{ $ci }}][{{ $ai }}]" aria-label="Portal-Schwimmer für {{ $athlete['firstname'] }} {{ $athlete['lastname'] }}"
                                                        class="flex-1 px-3 py-1.5 border border-amber-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-amber-50">
                                                    <option value="0">– überspringen –</option>
                                                    @foreach($swimmers as $sw)
                                                        <option value="{{ $sw->id }}">{{ $sw->firstname }} {{ $sw->lastname }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <p class="text-xs text-amber-700 mt-0.5 ml-4">Nicht automatisch erkannt</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

        </fieldset>
        @endforeach

        {{-- Aktions-Leiste --}}
        <x-ui.import-bar :cancel="route('trainer.dsv-import.index')"
                         count="fieldset:not([disabled]) select[name^='mappings'] option:checked:not([value='0'])"
                         singular="zugeordneten Schwimmer" plural="zugeordnete Schwimmer">
                @php
                    $totalAthletes = collect($parsed['meets'][0]['clubs'] ?? [])->sum(fn($c) => count($c['athletes']));
                    $autoMatched   = collect($parsed['meets'][0]['clubs'] ?? [])->sum(fn($c) =>
                        collect($c['athletes'])->filter(fn($a) => $a['matched_user_id'])->count()
                    );
                    $totalResults  = collect($parsed['meets'][0]['clubs'] ?? [])->sum(fn($c) =>
                        collect($c['athletes'])->sum(fn($a) => count($a['results']))
                    );
                @endphp
                <strong>{{ $autoMatched }}/{{ $totalAthletes }}</strong> automatisch erkannt ·
                <strong>{{ $totalResults }}</strong> Ergebnisse in der Datei
        </x-ui.import-bar>

    </form>
</div>
@endsection
