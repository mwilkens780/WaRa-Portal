@extends('layouts.app')
@section('title', 'Datenprüfung Training')
@section('page-title', 'Datenprüfung Training')

@section('content')
<div class="mt-2 space-y-5">
    <x-ui.alert tone="info">
        Die Befunde sind nur lesend – sie zeigen, wo Trainingsserien und Hallenbelegungen nicht
        zusammenpassen. Doppelte Belegungen einer Serie werden beim nächsten Speichern der Serie
        („Serie speichern“) automatisch zusammengelegt. Geändert wird nur über „Übernahme ausführen“.
    </x-ui.alert>

    <x-ui.card title="Übernahme in Trainingsserien" :meta="$pending ? $pending . ' Serien noch nicht übernommen' : 'Alle Serien übernommen'">
        <p class="text-sm text-gray-700">
            Legt je Serie einen eigenen Datensatz an (Werte der nächsten Einheit), markiert Einheiten mit
            abweichenden Werten und hängt die Hallenbelegungen an die Serie. Unverknüpfte Belegungen werden nur
            verbunden, wenn Zeit <strong>und</strong> Gruppe passen; identische Kopien werden zusammengelegt.
            Vorhandene Serien bleiben unverändert – die Übernahme kann wiederholt werden.
        </p>
        @if($pending)
            <div class="mt-3 flex flex-wrap gap-2">
                <x-ui.button variant="secondary" href="{{ route('admin.training-audit', ['vorschau' => 1]) }}#uebernahme">Probelauf anzeigen</x-ui.button>
                @if($preview)
                    <form method="POST" action="{{ route('admin.training-audit.backfill') }}"
                          data-confirm="Übernahme ausführen?"
                          data-confirm-text="Legt {{ $preview['totals']['serien'] }} Serien an und entfernt {{ $preview['totals']['entfernt'] }} doppelte Belegungen – so wie im Probelauf gezeigt."
                          data-confirm-label="Ausführen">
                        @csrf
                        <x-ui.button type="submit">Übernahme ausführen</x-ui.button>
                    </form>
                @endif
            </div>
        @endif
    </x-ui.card>

    @if($preview)
        @php $t = $preview['totals']; @endphp
        <x-ui.card id="uebernahme" title="Probelauf – nichts gespeichert" :meta="$t['serien'] . ' Serien, ' . $t['abweichungen'] . ' abweichende Einheiten, ' . $t['verbunden'] . ' Belegungen verbunden, ' . $t['entfernt'] . ' doppelte entfernt'" :padded="false">
            @if($preview['rows'])
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Probelauf Übernahme in Trainingsserien</caption>
                        <thead class="bg-gray-50 border-y border-gray-200">
                            <tr>
                                @foreach(array_keys($preview['rows'][0]) as $spalte)
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ str_replace('_', ' ', $spalte) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($preview['rows'] as $row)
                                <tr>
                                    @foreach($row as $wert)
                                        <td class="px-4 py-2 text-gray-700 tabular-nums">{{ $wert }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="px-5 py-3 text-sm text-gray-700">Keine neuen Serien.</p>
            @endif
        </x-ui.card>
    @endif

    @php $gesamt = collect($result)->sum(fn($b) => count($b['rows'])); @endphp
    @if($gesamt === 0)
        <x-ui.empty-state icon="check-circle" title="Keine Auffälligkeiten" text="Serien und Hallenbelegungen passen zusammen." />
    @endif

    @foreach($result as $key => $block)
        <x-ui.card :title="$block['title']" :meta="count($block['rows']) . ' Befund' . (count($block['rows']) === 1 ? '' : 'e')" :padded="false">
            <p class="px-5 pt-3 text-sm text-gray-700">{{ $block['explain'] }}</p>
            @if($block['rows'])
                <div class="overflow-x-auto mt-3">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ $block['title'] }}</caption>
                        <thead class="bg-gray-50 border-y border-gray-200">
                            <tr>
                                @foreach(array_keys($block['rows'][0]) as $spalte)
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">{{ str_replace('_', ' ', $spalte) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($block['rows'] as $row)
                                <tr>
                                    @foreach($row as $spalte => $wert)
                                        <td class="px-4 py-2 text-gray-700 {{ $spalte === 'serie_id' ? 'font-mono text-xs' : '' }}">
                                            @if($spalte === 'serie_id')
                                                <a href="{{ route('trainer.sessions.series.show', $wert) }}" class="text-primary underline underline-offset-2 hover:no-underline">Serie öffnen</a>
                                            @else
                                                {{ $wert }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="px-5 py-3 text-sm text-green-700">Keine Befunde.</p>
            @endif
        </x-ui.card>
    @endforeach
</div>
@endsection
