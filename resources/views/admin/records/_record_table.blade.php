@php
    $label = $type === 'vereinsrekord' ? 'Vereinsrekorde' : 'Landesrekorde';

    // Abschnitte je Lage + Geschlecht, jede Zeile = [course, distance, record|null]
    $sections = [];

    if ($type === 'vereinsrekord') {
        // Vereinsrekorde: die komplette Streckenliste, auch ohne bestehenden
        // Rekord - so sieht man, welche Rekorde noch offen sind.
        $byKey = $records->keyBy(fn($r) => "{$r->discipline}_{$r->gender}_{$r->course}_{$r->distance}");
        foreach (['F', 'R', 'B', 'S', 'L'] as $disc) {
            foreach (array_keys($recordGenders) as $gender) {
                $rows = [];
                foreach (\App\Models\Record::VR_EVENTS as $course => $events) {
                    foreach ($events[$disc] ?? [] as $dist) {
                        $rows[] = [$course, $dist, $byKey->get("{$disc}_{$gender}_{$course}_{$dist}")];
                    }
                }
                $sections[] = ['discipline' => $disc, 'gender' => $gender, 'rows' => $rows];
            }
        }
    } else {
        foreach ($records->groupBy(fn($r) => $r->discipline . '_' . $r->gender) as $group) {
            $first = $group->first();
            $sections[] = [
                'discipline' => $first->discipline,
                'gender'     => $first->gender,
                'rows'       => $group->sortBy('distance')->map(fn($r) => [$r->course, $r->distance, $r])->values()->all(),
            ];
        }
    }

    $discLabels = ['F' => 'Freistil', 'R' => 'Rücken', 'B' => 'Brust', 'S' => 'Schmetterling', 'L' => 'Lagen'];
@endphp

@if(empty($sections))
    <p class="text-sm text-gray-600 text-center px-5 py-10">
        Noch keine {{ $label }} hinterlegt.
    </p>
@else
    <div>
    @foreach($sections as $section)
        <div class="record-section border-b border-gray-100 last:border-0"
             data-gender="{{ $section['gender'] }}" x-show="activeGender === '{{ $section['gender'] }}'">
            <div class="bg-gray-50 px-5 py-2 flex items-center gap-2">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    {{ $discLabels[$section['discipline']] ?? $section['discipline'] }}
                    · {{ \App\Support\Gender::title($section['gender']) }}
                </p>
            </div>
            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tabelle (waagerecht scrollbar)">
            {{-- Feste Spaltenbreiten: alle Abschnitte stehen exakt untereinander.
                 Die letzte Spalte ist leer und nimmt den Rest der Breite auf -
                 sonst wuerde der Name auf grossen Bildschirmen riesig. Die
                 Spaltenbreiten geben zugleich die Mindestbreite der Tabelle vor,
                 ein zusaetzliches min-w waere nur irrefuehrend. --}}
            <x-ui.table :card="false" caption="Rekorde" x-data="{ openRow: null }" class="table-fixed">
<x-slot:head>
                        <x-ui.th class="w-20">Strecke</x-ui.th>
                        <x-ui.th class="w-24">Bahn</x-ui.th>
                        <x-ui.th align="right" class="w-28">Zeit</x-ui.th>
                        <x-ui.th class="w-64">Name</x-ui.th>
                        <x-ui.th class="w-28">Datum</x-ui.th>
                        <x-ui.th class="w-48">Ort</x-ui.th>
                        <x-ui.th class="w-24">System</x-ui.th>
                        @if($isAdmin) <x-ui.th sr class="w-40">Aktionen</x-ui.th> @endif
                        <x-ui.th sr>Aktionen</x-ui.th>
                    </x-slot:head>
                    @foreach($section['rows'] as [$course, $distance, $record])
                        <tr class="record-row hover:bg-gray-50"
                            data-course="{{ $course }}"
                            x-show="activeCourse === '{{ $course }}'">
                            <x-ui.td class="font-medium text-gray-800">{{ $distance }} m</x-ui.td>
                            <x-ui.td muted class="text-xs">{{ $course }}</x-ui.td>
                            @if($record)
                                <x-ui.td align="right" class="tabular-nums font-mono font-bold text-primary">{{ $record->formatted_time }}</x-ui.td>
                                <x-ui.td class="text-gray-700 truncate" title="{{ $record->swimmer_name }}">
                                    {{ $record->swimmer_name }}
                                    @if($record->user)
                                        <span class="text-xs text-green-700 ml-1">✓</span>
                                    @endif
                                </x-ui.td>
                                <x-ui.td muted class="text-xs">
                                    {{ $record->set_date?->format('d.m.Y') ?? $record->set_year ?? '–' }}
                                </x-ui.td>
                                <x-ui.td muted class="text-xs truncate" title="{{ $record->location }}">
                                    {{ $record->location ?? '–' }}
                                </x-ui.td>
                                <x-ui.td>
                                    @if($record->competitionResult)
                                        <span class="text-xs text-green-700 font-medium">Im Portal</span>
                                    @else
                                        <span class="text-xs text-gray-600">Extern</span>
                                    @endif
                                </x-ui.td>
                                @if($isAdmin)
                                <x-ui.td align="right" class="whitespace-nowrap">
                                    <button type="button" @click="openRow = openRow === {{ $record->id }} ? null : {{ $record->id }}"
                                            class="text-xs text-gray-500 hover:text-primary">Bearbeiten</button>
                                    <form method="POST" class="inline" action="{{ route('admin.records.destroy', $record) }}"
                                          data-confirm="Rekord löschen?" data-confirm-label="Löschen" data-confirm-danger>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-xs ml-1">Löschen</button>
                                    </form>
                                </x-ui.td>
                                @endif
                            @else
                                <x-ui.td align="right" muted class="tabular-nums font-mono">–</x-ui.td>
                                <x-ui.td muted class="text-xs italic" colspan="4">noch kein Rekord</x-ui.td>
                                @if($isAdmin) <x-ui.td></x-ui.td> @endif
                            @endif
                            <x-ui.td></x-ui.td>
                        </tr>

                        @if($isAdmin && $record)
                        {{-- Bearbeiten: Korrektur von Name, Zeit, Datum und Ort.
                             Strecke, Bahn und Geschlecht bleiben fest. --}}
                        <tr x-show="openRow === {{ $record->id }} && activeCourse === '{{ $course }}'" x-cloak class="bg-blue-50/40">
                            <x-ui.td colspan="{{ $isAdmin ? 9 : 8 }}">
                                <form method="POST" action="{{ route('admin.records.update', $record) }}"
                                      class="flex flex-wrap items-end gap-3">
                                    @csrf @method('PUT')
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Name</label>
                                        <input aria-label="Name" type="text" name="swimmer_name" value="{{ $record->swimmer_name }}" required
                                               class="px-2 py-1.5 border border-gray-300 rounded text-xs w-48 outline-none focus:ring-1 focus:ring-primary/40">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Zeit (Min : Sek , 1/100)</label>
                                        @php
                                            $ms = $record->time_ms;
                                            $mm = intdiv($ms, 60000); $ss = intdiv($ms % 60000, 1000); $cs = intdiv($ms % 1000, 10);
                                        @endphp
                                        <div class="flex items-center gap-1">
                                            <input type="number" name="time_minutes" value="{{ $mm }}" min="0" class="w-14 px-2 py-1.5 border border-gray-300 rounded text-xs text-center">
                                            <span class="text-gray-600">:</span>
                                            <input type="number" name="time_seconds" value="{{ $ss }}" min="0" max="59" required class="w-14 px-2 py-1.5 border border-gray-300 rounded text-xs text-center">
                                            <span class="text-gray-600">,</span>
                                            <input type="number" name="time_cs" value="{{ $cs }}" min="0" max="99" required class="w-14 px-2 py-1.5 border border-gray-300 rounded text-xs text-center">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Datum</label>
                                        <input aria-label="Datum" type="date" name="set_date" value="{{ $record->set_date?->format('Y-m-d') }}"
                                               class="px-2 py-1.5 border border-gray-300 rounded text-xs outline-none focus:ring-1 focus:ring-primary/40">
                                    </div>
                                    <div class="flex-1 min-w-[160px]">
                                        <label class="block text-xs text-gray-500 mb-1">Veranstaltung / Ort</label>
                                        <input aria-label="Veranstaltung / Ort" type="text" name="location" value="{{ $record->location }}"
                                               class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs outline-none focus:ring-1 focus:ring-primary/40">
                                    </div>
                                    <button type="submit" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-dark transition-colors">Speichern</button>
                                    <button type="button" @click="openRow = null" class="px-2.5 py-1.5 border border-gray-200 text-gray-500 rounded-lg text-xs">Abbrechen</button>
                                </form>
                            </x-ui.td>
                        </tr>
                        @endif
                    @endforeach
                </x-ui.table>
            </div>
        </div>
    @endforeach
    </div>
@endif
