@php
    $label = $type === 'vereinsrekord' ? 'Vereinsrekorde' : 'Landesrekorde';

    // Abschnitte je Lage + Geschlecht, jede Zeile = [course, distance, record|null]
    $sections = [];

    if ($type === 'vereinsrekord') {
        // Vereinsrekorde: die komplette Streckenliste, auch ohne bestehenden
        // Rekord - so sieht man, welche Rekorde noch offen sind.
        $byKey = $records->keyBy(fn($r) => "{$r->discipline}_{$r->gender}_{$r->course}_{$r->distance}");
        foreach (['F', 'R', 'B', 'S', 'L'] as $disc) {
            foreach (['M', 'F'] as $gender) {
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
    <p class="text-sm text-gray-400 text-center px-5 py-10">
        Noch keine {{ $label }} hinterlegt.
    </p>
@else
    <div>
    @foreach($sections as $section)
        <div class="record-section border-b border-gray-100 last:border-0"
             data-gender="{{ $section['gender'] }}">
            <div class="bg-gray-50 px-5 py-2 flex items-center gap-2">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    {{ $discLabels[$section['discipline']] ?? $section['discipline'] }}
                    · {{ $section['gender'] === 'M' ? 'Männlich' : 'Weiblich' }}
                </p>
            </div>
            <div class="overflow-x-auto">
            {{-- Feste Spaltenbreiten: alle Abschnitte der Liste stehen exakt untereinander --}}
            <table class="w-full text-sm table-fixed min-w-[760px]">
                <colgroup>
                    <col class="w-20">      {{-- Strecke --}}
                    <col class="w-24">      {{-- Bahn --}}
                    <col class="w-24">      {{-- Zeit --}}
                    <col>                   {{-- Name: Rest --}}
                    <col class="w-24">      {{-- Datum --}}
                    <col class="w-44">      {{-- Ort --}}
                    <col class="w-24">      {{-- System --}}
                    @if($isAdmin) <col class="w-20"> @endif
                </colgroup>
                <thead>
                    <tr class="border-b border-gray-50">
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Strecke</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Bahn</th>
                        <th class="px-5 py-2 text-right text-xs text-gray-400 font-medium">Zeit</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Name</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Datum</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Ort</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">System</th>
                        @if($isAdmin) <th class="px-3 py-2"></th> @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($section['rows'] as [$course, $distance, $record])
                        <tr class="record-row hover:bg-gray-50"
                            data-course="{{ $course }}"
                            x-show="activeCourse === '{{ $course }}'">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ $distance }} m</td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ $course }}</td>
                            @if($record)
                                <td class="text-right tabular-nums px-5 py-2.5 font-mono font-bold text-primary">{{ $record->formatted_time }}</td>
                                <td class="px-5 py-2.5 text-gray-700 truncate" title="{{ $record->swimmer_name }}">
                                    {{ $record->swimmer_name }}
                                    @if($record->user)
                                        <span class="text-xs text-green-600 ml-1">✓</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-gray-500 text-xs">
                                    {{ $record->set_date?->format('d.m.Y') ?? '–' }}
                                </td>
                                <td class="px-5 py-2.5 text-gray-400 text-xs truncate" title="{{ $record->location }}">
                                    {{ $record->location ?? '–' }}
                                </td>
                                <td class="px-5 py-2.5">
                                    @if($record->competitionResult)
                                        <span class="text-xs text-green-600 font-medium">Im Portal</span>
                                    @else
                                        <span class="text-xs text-gray-400">Extern</span>
                                    @endif
                                </td>
                                @if($isAdmin)
                                <td class="px-3 py-2.5 text-right">
                                    <form method="POST" action="{{ route('admin.records.destroy', $record) }}"
                                          onsubmit="return confirm('Rekord löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600 text-xs">Löschen</button>
                                    </form>
                                </td>
                                @endif
                            @else
                                <td class="text-right tabular-nums px-5 py-2.5 font-mono text-gray-300">–</td>
                                <td class="px-5 py-2.5 text-xs text-gray-300 italic" colspan="4">noch kein Rekord</td>
                                @if($isAdmin) <td></td> @endif
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endforeach
    </div>
@endif
