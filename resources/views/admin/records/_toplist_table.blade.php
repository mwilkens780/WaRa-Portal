{{--
    Berechnete Bestenliste: je Strecke die 10 schnellsten Schwimmer,
    jahrgangsuebergreifend. Zeilen aus Wettkampfergebnissen sind nicht
    editierbar (dort wird das Ergebnis korrigiert), historische und von Hand
    angelegte Eintraege schon.

    Erwartet: $lists (aus BestListService), $course, $isAdmin, $tab
--}}
@php
    $discLabels = ['F' => 'Freistil', 'R' => 'Rücken', 'B' => 'Brust', 'S' => 'Schmetterling', 'L' => 'Lagen'];
    $genderLabels = ['F' => 'Weiblich', 'M' => 'Männlich'];
    $isEmpty = collect($lists)->flatten(1)->flatten(1)->isEmpty();
@endphp

@if($isEmpty)
    <p class="text-sm text-gray-400 text-center px-5 py-10">
        Für die {{ $course }} sind noch keine Leistungen erfasst.
    </p>
@else
    @foreach(['F' => 'Weiblich', 'M' => 'Männlich'] as $gender => $genderLabel)
        <div class="border-b border-gray-100 last:border-0">
            <div class="bg-gray-100 px-5 py-2">
                <p class="text-xs font-bold text-gray-700 uppercase tracking-wider">{{ $genderLabel }} · {{ $course }}</p>
            </div>

            @foreach($lists[$gender] ?? [] as $key => $rows)
                @php [$disc, $dist] = explode('_', $key); @endphp
                <div class="border-t border-gray-50 first:border-t-0">
                    <div class="bg-gray-50 px-5 py-1.5 flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-600">{{ $dist }} m {{ $discLabels[$disc] ?? $disc }}</span>
                        @if(empty($rows))
                            <span class="text-xs text-gray-400">· noch keine Zeiten</span>
                        @endif
                    </div>

                    @if(!empty($rows))
                    <div class="overflow-x-auto">
                        {{-- Letzte Spalte bleibt leer und nimmt den Rest der Breite auf,
                             damit die Namensspalte nicht ins Riesenhafte waechst.
                             Die Spaltenbreiten sind zugleich die Mindestbreite. --}}
                        <table class="w-full text-sm table-fixed">
                            <colgroup>
                                <col class="w-12"><col class="w-64"><col class="w-24"><col class="w-28"><col class="w-20"><col class="w-28">
                                @if($isAdmin) <col class="w-40"> @endif
                                <col>
                            </colgroup>
                            <thead>
                                <tr class="border-b border-gray-50">
                                    <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">#</th>
                                    <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Name</th>
                                    <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Jahrgang</th>
                                    <th class="px-5 py-1.5 text-right text-xs text-gray-400 font-medium">Zeit</th>
                                    <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Jahr</th>
                                    <th class="px-5 py-1.5 text-left text-xs text-gray-400 font-medium">Quelle</th>
                                    @if($isAdmin) <th class="px-3 py-1.5"></th> @endif
                                    <th></th>
                                </tr>
                            </thead>
                            {{-- openRow: Knopf und Bearbeiten-Formular stehen in verschiedenen <tr> --}}
                            <tbody class="divide-y divide-gray-50" x-data="{ openRow: null }">
                                @foreach($rows as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-2 text-xs text-gray-400 font-medium">{{ $row['rank'] }}</td>
                                        <td class="px-5 py-2 text-gray-800 truncate" title="{{ $row['name'] }}">{{ $row['name'] }}</td>
                                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $row['birth_year'] ?? '–' }}</td>
                                        <td class="px-5 py-2 text-right tabular-nums font-mono font-bold {{ $row['rank'] === 1 ? 'text-primary' : 'text-gray-700' }}">
                                            {{ \App\Models\SwimmingTime::formatMs($row['time_ms']) }}
                                        </td>
                                        <td class="px-5 py-2 text-gray-500 text-xs">{{ $row['year'] ?? '–' }}</td>
                                        <td class="px-5 py-2">
                                            @if($row['source'] === 'portal')
                                                <span class="text-xs text-green-600" title="{{ $row['event'] }}">Wettkampf</span>
                                            @else
                                                <span class="text-xs text-gray-400" title="{{ $row['event'] }}">historisch</span>
                                            @endif
                                        </td>
                                        @if($isAdmin)
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            @if($row['entry_id'])
                                                <button type="button" @click="openRow = openRow === {{ $row['entry_id'] }} ? null : {{ $row['entry_id'] }}"
                                                        class="text-xs text-gray-500 hover:text-primary">Bearbeiten</button>
                                                <form method="POST" class="inline"
                                                      action="{{ route('admin.bestlist.destroy', ['bestListEntry' => $row['entry_id'], 'tab' => $tab]) }}"
                                                      onsubmit="return confirm('Eintrag löschen?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-xs text-red-400 hover:text-red-600 ml-1">Löschen</button>
                                                </form>
                                            @else
                                                <a href="{{ route('admin.competitions.index') }}"
                                                   class="text-xs text-gray-300" title="Ergebnis im Wettkampf korrigieren">aus Ergebnis</a>
                                            @endif
                                        </td>
                                        @endif
                                        <td></td>
                                    </tr>

                                    @if($isAdmin && $row['entry_id'])
                                    {{-- Bearbeiten: nur historische / manuelle Eintraege --}}
                                    <tr x-show="openRow === {{ $row['entry_id'] }}" x-cloak class="bg-blue-50/40">
                                        <td colspan="{{ $isAdmin ? 8 : 7 }}" class="px-5 py-3">
                                            <form method="POST" action="{{ route('admin.bestlist.update', ['bestListEntry' => $row['entry_id'], 'tab' => $tab]) }}"
                                                  class="flex flex-wrap items-end gap-3">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="discipline" value="{{ $disc }}">
                                                <input type="hidden" name="distance" value="{{ $dist }}">
                                                <input type="hidden" name="gender" value="{{ $gender }}">
                                                <input type="hidden" name="course" value="{{ $course }}">
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-1">Name</label>
                                                    <input type="text" name="swimmer_name" value="{{ $row['name'] }}" required
                                                           class="px-2 py-1.5 border border-gray-300 rounded text-xs w-48 outline-none focus:ring-1 focus:ring-primary/40">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-1">Jahrgang</label>
                                                    <input type="number" name="birth_year" value="{{ $row['birth_year'] }}" min="1900" max="{{ now()->year }}"
                                                           class="px-2 py-1.5 border border-gray-300 rounded text-xs w-20 outline-none focus:ring-1 focus:ring-primary/40">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-1">Zeit (Min : Sek , 1/100)</label>
                                                    @php
                                                        $ms = $row['time_ms'];
                                                        $mm = intdiv($ms, 60000); $ss = intdiv($ms % 60000, 1000); $cs = intdiv($ms % 1000, 10);
                                                    @endphp
                                                    <div class="flex items-center gap-1">
                                                        <input type="number" name="time_minutes" value="{{ $mm }}" min="0" class="w-14 px-2 py-1.5 border border-gray-300 rounded text-xs text-center">
                                                        <span class="text-gray-400">:</span>
                                                        <input type="number" name="time_seconds" value="{{ $ss }}" min="0" max="59" required class="w-14 px-2 py-1.5 border border-gray-300 rounded text-xs text-center">
                                                        <span class="text-gray-400">,</span>
                                                        <input type="number" name="time_cs" value="{{ $cs }}" min="0" max="99" required class="w-14 px-2 py-1.5 border border-gray-300 rounded text-xs text-center">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] text-gray-500 mb-1">Jahr</label>
                                                    <input type="number" name="set_year" value="{{ $row['year'] }}" min="1900" max="{{ now()->year }}" required
                                                           class="px-2 py-1.5 border border-gray-300 rounded text-xs w-20 outline-none focus:ring-1 focus:ring-primary/40">
                                                </div>
                                                <div class="flex-1 min-w-[160px]">
                                                    <label class="block text-[10px] text-gray-500 mb-1">Veranstaltung / Ort</label>
                                                    <input type="text" name="location" value="{{ $row['event'] }}"
                                                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs outline-none focus:ring-1 focus:ring-primary/40">
                                                </div>
                                                <button type="submit" class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-dark transition-colors">Speichern</button>
                                                <button type="button" @click="openRow = null" class="px-2.5 py-1.5 border border-gray-200 text-gray-500 rounded-lg text-xs">Abbrechen</button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
@endif
