@php
    $grouped = $entries
        ->groupBy(fn($e) => $e->discipline . '_' . $e->gender);
    $emptyLabel = $listType === 'eternal' ? 'Ewige Bestenlisten' : 'Jahresbestenlisten';
@endphp

@if($entries->isEmpty())
    <p class="text-sm text-gray-400 text-center px-5 py-10">
        Noch keine {{ $emptyLabel }}-Einträge vorhanden.
    </p>
@else
    <div>
    @foreach($grouped as $key => $group)
        @php $first = $group->first(); @endphp
        <div class="border-b border-gray-100 last:border-0"
             x-show="true">
            <div class="bg-gray-50 px-5 py-2">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    {{ $first->discipline_label }}
                    · {{ $first->gender === 'M' ? 'Männlich' : 'Weiblich' }}
                </p>
            </div>

            @php
                $byBirthYear = $group->groupBy('birth_year')->sortKeys();
            @endphp

            @foreach($byBirthYear as $birthYear => $yearGroup)
                @php
                    $lbGroup  = $yearGroup->filter(fn($e) => $e->course === 'Langbahn')->sortBy('time_ms')->values();
                    $kbGroup  = $yearGroup->filter(fn($e) => $e->course === 'Kurzbahn')->sortBy('time_ms')->values();
                    $hasLb    = $lbGroup->isNotEmpty();
                    $hasKb    = $kbGroup->isNotEmpty();
                @endphp

                @if($hasLb)
                <div x-show="activeCourse === 'Langbahn'">
                    <div class="bg-blue-50/30 px-5 py-1.5 flex items-center gap-2">
                        <span class="text-xs font-medium text-blue-700">Jahrgang {{ $birthYear }} — Langbahn</span>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-50">
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium w-8">#</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Strecke</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Zeit</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Name</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Datum</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Ort</th>
                                @if($isAdmin) <th class="px-3 py-1.5"></th> @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($lbGroup->groupBy('distance')->sortKeys() as $dist => $distGroup)
                                @foreach($distGroup->sortBy('time_ms')->values() as $rank => $entry)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-2 text-xs text-gray-400 font-medium">{{ $rank + 1 }}</td>
                                    <td class="px-5 py-2 font-medium text-gray-800">{{ $dist }} m</td>
                                    <td class="px-5 py-2 font-mono font-bold {{ $rank === 0 ? 'text-primary' : 'text-gray-700' }}">
                                        {{ $entry->formatted_time }}
                                    </td>
                                    <td class="px-5 py-2 text-gray-700">
                                        {{ $entry->swimmer_name }}
                                        @if($entry->user) <span class="text-xs text-green-600 ml-1">✓</span> @endif
                                    </td>
                                    <td class="px-5 py-2 text-gray-500 text-xs">{{ $entry->set_date?->format('d.m.Y') ?? '–' }}</td>
                                    <td class="px-5 py-2 text-gray-400 text-xs max-w-[140px] truncate">{{ $entry->location ?? '–' }}</td>
                                    @if($isAdmin)
                                    <td class="px-3 py-2 text-right">
                                        <form method="POST" action="{{ route('admin.bestlist.destroy', $entry) }}"
                                              onsubmit="return confirm('Eintrag löschen?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600 text-xs">Löschen</button>
                                        </form>
                                    </td>
                                    @endif
                                </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
                @endif

                @if($hasKb)
                <div x-show="activeCourse === 'Kurzbahn'">
                    <div class="bg-teal-50/30 px-5 py-1.5 flex items-center gap-2">
                        <span class="text-xs font-medium text-teal-700">Jahrgang {{ $birthYear }} — Kurzbahn</span>
                    </div>
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-50">
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium w-8">#</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Strecke</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Zeit</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Name</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Datum</th>
                                <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Ort</th>
                                @if($isAdmin) <th class="px-3 py-1.5"></th> @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($kbGroup->groupBy('distance')->sortKeys() as $dist => $distGroup)
                                @foreach($distGroup->sortBy('time_ms')->values() as $rank => $entry)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-2 text-xs text-gray-400 font-medium">{{ $rank + 1 }}</td>
                                    <td class="px-5 py-2 font-medium text-gray-800">{{ $dist }} m</td>
                                    <td class="px-5 py-2 font-mono font-bold {{ $rank === 0 ? 'text-primary' : 'text-gray-700' }}">
                                        {{ $entry->formatted_time }}
                                    </td>
                                    <td class="px-5 py-2 text-gray-700">
                                        {{ $entry->swimmer_name }}
                                        @if($entry->user) <span class="text-xs text-green-600 ml-1">✓</span> @endif
                                    </td>
                                    <td class="px-5 py-2 text-gray-500 text-xs">{{ $entry->set_date?->format('d.m.Y') ?? '–' }}</td>
                                    <td class="px-5 py-2 text-gray-400 text-xs max-w-[140px] truncate">{{ $entry->location ?? '–' }}</td>
                                    @if($isAdmin)
                                    <td class="px-3 py-2 text-right">
                                        <form method="POST" action="{{ route('admin.bestlist.destroy', $entry) }}"
                                              onsubmit="return confirm('Eintrag löschen?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600 text-xs">Löschen</button>
                                        </form>
                                    </td>
                                    @endif
                                </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    @endforeach
    </div>
@endif
