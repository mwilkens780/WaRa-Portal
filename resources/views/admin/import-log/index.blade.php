@extends('layouts.app')
@section('title', 'Import-Log & Crawler')
@section('page-title', 'Import-Log & Crawler')

@section('content')
<div class="space-y-5">

    {{-- Flash-Nachrichten --}}
    @if(session('crawler_result'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl font-medium">
            {{ session('crawler_result') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-xl font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Crawler-Status-Karten --}}
    <div>
        <h2 class="text-sm font-bold text-gray-700 mb-2">Crawler-Status</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach($crawlerStats as $source => $info)
            @php
                $last   = $info['last_entry'];
                $hasErr = $info['count_errors'] > 0;
                $lastStatus = $last?->status;
                $borderCls = $lastStatus === 'error' ? 'border-red-200' : ($lastStatus === 'success' ? 'border-green-200' : 'border-gray-100');
            @endphp
            <div class="bg-white rounded-xl shadow-sm border {{ $borderCls }} p-4 flex flex-col gap-3">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-gray-800">{{ $info['label'] }}</p>
                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $info['schedule'] }}</p>
                    </div>
                    @if($last)
                        @if($lastStatus === 'success')
                            <span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Zuletzt OK</span>
                        @elseif($lastStatus === 'error')
                            <span class="text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Zuletzt Fehler</span>
                        @else
                            <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Zuletzt übersprungen</span>
                        @endif
                    @else
                        <span class="text-[10px] bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Noch nie gelaufen</span>
                    @endif
                </div>

                {{-- Letzter Lauf --}}
                @if($last)
                    <div class="text-xs text-gray-500">
                        <span class="font-medium text-gray-700">Letzter Eintrag:</span>
                        {{ $last->imported_at?->format('d.m.Y H:i') ?? '–' }}
                        @if($last->message)
                            <br><span class="text-gray-400 italic">{{ Str::limit($last->message, 70) }}</span>
                        @endif
                    </div>
                @endif

                {{-- Statistik --}}
                <div class="flex gap-3 text-xs">
                    <span class="text-green-700 font-semibold">{{ number_format($info['count_success']) }} importiert</span>
                    <span class="text-gray-400">{{ number_format($info['count_skipped']) }} übersprungen</span>
                    @if($info['count_errors'] > 0)
                        <span class="text-red-600 font-semibold">{{ $info['count_errors'] }} Fehler</span>
                    @endif
                </div>

                {{-- Index-URL --}}
                @if($info['url'])
                    <a href="{{ $info['url'] }}" target="_blank" class="text-[10px] text-primary hover:underline truncate">
                        {{ $info['url'] }}
                    </a>
                @endif

                {{-- Hinweis --}}
                @if(!empty($info['note']))
                    <p class="text-[10px] text-amber-600 bg-amber-50 rounded px-2 py-1">{{ $info['note'] }}</p>
                @endif

                {{-- Manueller Start --}}
                @if(empty($info['note']))
                    <form method="POST" action="{{ route('admin.import-log.run', $source) }}">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('Crawler \"{{ $info['label'] }}\" jetzt manuell starten? Dies kann einige Minuten dauern.')"
                                class="w-full px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                            Jetzt ausführen
                        </button>
                    </form>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Cron-Hinweis --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-800">
        <strong>Cron auf dem Server prüfen:</strong>
        Damit Crawler automatisch laufen, muss folgender Cron-Eintrag aktiv sein:<br>
        <code class="font-mono bg-amber-100 px-1.5 py-0.5 rounded mt-1 inline-block">* * * * * php /www/htdocs/w007ba65/wara-portal.de/artisan schedule:run >> /dev/null 2>&1</code><br>
        Alternativ ist auch der HTTP-Endpunkt konfiguriert: <code class="font-mono bg-amber-100 px-1">GET /cron/{token}</code>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" action="{{ route('admin.import-log.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Quelle</label>
                <select name="source" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Alle</option>
                    @foreach(['shsv' => 'SHSV', 'nsv' => 'NSV', 'dsvdata' => 'DSV-Daten', 'dsv' => 'DSV National', 'webclub_batch' => 'WebClub-Batch', 'manual' => 'Manuell'] as $v => $l)
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

    {{-- Log-Tabelle --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if($logs->isEmpty())
            <div class="text-center py-12">
                <svg class="mx-auto w-10 h-10 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm text-gray-400">Keine Import-Einträge gefunden.</p>
                <p class="text-xs text-gray-300 mt-1">Wenn noch nie Einträge vorhanden waren, haben die Crawler noch nicht gelaufen oder der Cron ist nicht aktiv.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Zeitpunkt</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Quelle</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Datei / ID</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Wettkampf</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Meldung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50 {{ $log->isError() ? 'bg-red-50/40' : '' }}">
                                <td class="px-4 py-2.5 text-gray-500 text-xs whitespace-nowrap">
                                    {{ $log->imported_at?->format('d.m.Y H:i') ?? '–' }}
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
                                <td class="px-4 py-2.5 text-gray-500 text-xs whitespace-nowrap">
                                    {{ ['shsv' => 'SHSV', 'nsv' => 'NSV', 'dsvdata' => 'DSV-Daten', 'dsv' => 'DSV National', 'webclub_batch' => 'WebClub-Batch', 'manual' => 'Manuell'][$log->source] ?? $log->source }}
                                </td>
                                <td class="px-4 py-2.5 text-xs max-w-[200px]">
                                    @if($log->source_url)
                                        <a href="{{ $log->source_url }}" target="_blank" class="text-primary hover:underline font-mono break-all">
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
                                <td class="px-4 py-2.5 text-xs text-gray-500 max-w-xs" title="{{ $log->message }}">
                                    {{ Str::limit($log->message ?? '', 100) }}
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
