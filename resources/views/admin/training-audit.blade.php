@extends('layouts.app')
@section('title', 'Datenprüfung Training')
@section('page-title', 'Datenprüfung Training')

@section('content')
<div class="mt-2 space-y-5">
    <x-ui.alert tone="info">
        Nur lesend – diese Seite ändert nichts. Sie zeigt, wo Trainingsserien und Hallenbelegungen nicht
        zusammenpassen. Doppelte Belegungen einer Serie werden beim nächsten Speichern der Serie
        („Serie bearbeiten“) automatisch zusammengelegt.
    </x-ui.alert>

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
                                                <a href="{{ route('trainer.sessions.series.edit', $wert) }}" class="text-primary underline underline-offset-2 hover:no-underline">Serie öffnen</a>
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
