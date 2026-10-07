{{--
    Mannschaftswertung aus Staffeln (DMS-J): je Altersklasse und Geschlecht die
    Vereinsmannschaften nach Gesamtzeit. Daten: App\Services\Ranking\TeamRelayRanking
    Erwartet: $teamRanking, $competition
--}}
@php
    use App\Models\SwimmingTime;
    use App\Support\Gender;
    $discLabels = ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];
    $statusLabels = ['DQ' => 'disq.', 'AB' => 'abgem.', 'DNS' => 'n. a.', 'DNF' => 'aufg.', 'fehlt' => '–'];
    $ownWords = ['wasserratten'];
    $isOwn = fn(string $club) => collect($ownWords)->contains(fn($w) => str_contains(mb_strtolower($club), $w));
@endphp

<p class="text-sm text-gray-700">
    Wertung je Altersklasse: Summe der Staffelzeiten jeder Vereinsmannschaft. Eine disqualifizierte Staffel zählt mit der
    Zeit des Nachschwimmens (N); fehlt eine gültige Zeit, bleibt die Mannschaft ohne Gesamtzeit.
    Bei Gleichstand entscheidet die Lagenstaffel, dann Rücken, Brust, Schmetterling, Freistil.
</p>

@foreach($teamRanking as $class)
    @php
        $cols = [['label' => 'Platz', 'class' => 'w-14'], 'Mannschaft'];
        foreach ($class['disciplines'] as $d) $cols[] = ['label' => $discLabels[$d] ?? $d, 'align' => 'right', 'hide' => 'md'];
        $cols[] = ['label' => 'Gesamtzeit', 'align' => 'right'];
        $first = $class['teams']->first()['legs'] ?? [];
        $legLabel = collect($first)->map(fn($l) => $l['relay'] ? $l['relay']->distance_label : null)->filter()->unique()->implode(' / ');
    @endphp
    <x-ui.table :caption="'Mannschaftswertung ' . $class['class'] . ' ' . Gender::label($class['gender'])"
                :title="$class['class'] . ' · ' . (Gender::title($class['gender']) ?? '')"
                :meta="$legLabel ? $legLabel . ' m' : null"
                :columns="$cols" stack>
        @foreach($class['teams'] as $t)
            <tr class="{{ $isOwn($t['club']) ? 'bg-blue-50/60' : 'hover:bg-gray-50' }}">
                <x-ui.td label="Platz" strong>{{ $t['place'] ? $t['place'] . '.' : '–' }}</x-ui.td>
                <x-ui.td label="Mannschaft" :strong="$isOwn($t['club'])">
                    {{ $t['label'] }}
                    @if($t['missing'])
                        <span class="block text-xs font-normal text-red-700">ohne Gesamtzeit ({{ collect($t['missing'])->map(fn($d) => $discLabels[$d] ?? $d)->implode(', ') }})</span>
                    @endif
                </x-ui.td>
                @foreach($class['disciplines'] as $d)
                    @php $leg = $t['legs'][$d]; @endphp
                    <x-ui.td :label="$discLabels[$d] ?? $d" num hide="md">
                        @if($leg['valid'])
                            <span class="font-mono">{{ SwimmingTime::formatMs($leg['relay']->time_ms) }}</span>@if($leg['renatation'])<span class="text-xs text-amber-800" title="Nachschwimmen"> N</span>@endif
                        @else
                            <span class="text-xs text-red-700">{{ $statusLabels[$leg['status']] ?? $leg['status'] }}</span>
                        @endif
                    </x-ui.td>
                @endforeach
                <x-ui.td label="Gesamtzeit" num class="font-mono font-semibold">{{ $t['total_ms'] ? SwimmingTime::formatMs($t['total_ms']) : '–' }}</x-ui.td>
            </tr>
        @endforeach
    </x-ui.table>

    {{-- Eigene Mannschaften: Besetzung je Staffel --}}
    @foreach($class['teams']->filter(fn($t) => $isOwn($t['club'])) as $t)
        <details class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm">
            <summary class="cursor-pointer font-medium text-gray-800">Besetzung {{ $t['label'] }} – {{ $class['class'] }} {{ Gender::label($class['gender']) }}</summary>
            <ul class="mt-2 space-y-1 text-gray-700">
                @foreach($class['disciplines'] as $d)
                    @php $relay = $t['legs'][$d]['relay']; @endphp
                    @continue(!$relay)
                    <li><span class="font-medium">{{ $relay->distance_label }} m {{ $discLabels[$d] ?? $d }}:</span>
                        {{ $relay->members->map(fn($m) => $m->leg . '. ' . trim(($m->athlete?->firstname ?? '') . ' ' . ($m->athlete?->lastname ?? '')))->implode(', ') ?: '–' }}</li>
                @endforeach
            </ul>
        </details>
    @endforeach
@endforeach
