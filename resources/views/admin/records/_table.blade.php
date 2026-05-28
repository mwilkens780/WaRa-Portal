@if($records->isEmpty())
    <p class="text-sm text-gray-400 text-center px-5 py-10">
        Noch keine {{ $type === 'vereinsrekord' ? 'Vereinsrekorde' : 'Landesrekorde' }} hinterlegt.
        Trage Rekorde manuell ein oder importiere eine Rekordliste.
    </p>
@else
    @php
        $grouped = $records->groupBy(fn($r) => $r->discipline . '_' . $r->gender);
    @endphp
    @foreach($grouped as $key => $group)
        @php
            $first    = $group->first();
            $byDist   = $group->groupBy('distance')->sortKeys();
        @endphp
        <div class="border-b border-gray-100 last:border-0">
            <div class="bg-gray-50 px-5 py-2 flex items-center gap-2">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">
                    {{ $first->discipline_label }}
                    · {{ $first->gender === 'M' ? 'Männlich' : 'Weiblich' }}
                </p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-50">
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Strecke</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Wertung</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Bahn</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Zeit</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Name</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Datum</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">Ort</th>
                        <th class="px-5 py-2 text-left text-xs text-gray-400 font-medium">System</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($group->sortBy([['distance', 'asc'], ['age_group', 'asc']]) as $record)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ $record->distance }} m</td>
                            <td class="px-5 py-2.5">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $record->age_group ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $record->age_group_label }}
                                </span>
                            </td>
                            <td class="px-5 py-2.5 text-xs text-gray-500">{{ $record->course }}</td>
                            <td class="px-5 py-2.5 font-mono font-bold text-primary">{{ $record->formatted_time }}</td>
                            <td class="px-5 py-2.5 text-gray-700">
                                {{ $record->swimmer_name }}
                                @if($record->user)
                                    <span class="text-xs text-green-600 ml-1">✓</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-gray-500 text-xs">
                                {{ $record->set_date?->format('d.m.Y') ?? '–' }}
                            </td>
                            <td class="px-5 py-2.5 text-gray-400 text-xs max-w-[160px] truncate">
                                {{ $record->location ?? '–' }}
                            </td>
                            <td class="px-5 py-2.5">
                                @if($record->competitionResult)
                                    <span class="text-xs text-green-600 font-medium">Im Portal</span>
                                @else
                                    <span class="text-xs text-gray-400">Extern</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <form method="POST" action="{{ route('admin.records.destroy', $record) }}"
                                      onsubmit="return confirm('Rekord löschen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-600 text-xs">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif
