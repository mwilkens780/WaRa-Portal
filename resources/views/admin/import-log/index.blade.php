@extends('layouts.app')
@section('title', 'Import-Log')
@section('page-title', 'Import-Log')

@section('content')
<div class="space-y-4">

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" action="{{ route('admin.import-log.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Quelle</label>
                <select name="source" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Alle</option>
                    @foreach(['shsv' => 'SHSV', 'nsv' => 'NSV', 'dsv' => 'DSV', 'webclub_batch' => 'WebClub-Batch', 'manual' => 'Manuell'] as $v => $l)
                        <option value="{{ $v }}" {{ ($filters['source'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Alle</option>
                    <option value="success" {{ ($filters['status'] ?? '') === 'success' ? 'selected' : '' }}>Erfolg</option>
                    <option value="skipped" {{ ($filters['status'] ?? '') === 'skipped' ? 'selected' : '' }}>Übersprungen</option>
                    <option value="error"   {{ ($filters['status'] ?? '') === 'error'   ? 'selected' : '' }}>Fehler</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Von</label>
                <input type="date" name="von" value="{{ $filters['von'] ?? '' }}"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Bis</label>
                <input type="date" name="bis" value="{{ $filters['bis'] ?? '' }}"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                Filtern
            </button>
            @if(array_filter($filters))
                <a href="{{ route('admin.import-log.index') }}" class="px-4 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition-colors">
                    Zurücksetzen
                </a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if($logs->isEmpty())
            <div class="text-center py-12">
                <svg class="mx-auto w-10 h-10 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm text-gray-400">Noch keine Import-Einträge vorhanden.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Zeitpunkt</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Quelle</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Datei</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Wettkampf</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Meldung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50 {{ $log->isError() ? 'bg-red-50/30' : '' }}">
                                <td class="px-4 py-2.5 text-gray-500 text-xs whitespace-nowrap">
                                    {{ $log->imported_at?->deBerlin('d.m.Y H:i') ?? '–' }}
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    @if($log->isSuccess())
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Erfolg</span>
                                    @elseif($log->isSkipped())
                                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium">Übersprungen</span>
                                    @else
                                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">Fehler</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-gray-500 text-xs">
                                    {{ ['shsv' => 'SHSV', 'nsv' => 'NSV', 'dsv' => 'DSV', 'webclub_batch' => 'WebClub-Batch', 'manual' => 'Manuell'][$log->source] ?? $log->source }}
                                </td>
                                <td class="px-4 py-2.5 text-xs">
                                    @if($log->source_url)
                                        <a href="{{ $log->source_url }}" target="_blank" class="text-primary hover:underline font-mono">
                                            {{ $log->filename ?? basename($log->source_url) }}
                                        </a>
                                    @else
                                        <span class="text-gray-500 font-mono">{{ $log->filename ?? '–' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs">
                                    @if($log->competition)
                                        <a href="{{ route('admin.competitions.show', $log->competition) }}" class="text-primary hover:underline">
                                            {{ $log->competition->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-500 max-w-xs truncate" title="{{ $log->message }}">
                                    {{ $log->message ?? '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
