@extends('layouts.app')
@section('title', 'Import-Log & Crawler')
@section('page-title', 'Import-Log & Crawler')

@section('content')
<div class="space-y-5">

    {{-- Lebenszeichen des Server-Crons: ohne minuetlichen Aufruf fallen geplante Aufgaben aus --}}
    @php $cronOk = $schedulerLastRun && $schedulerLastRun->gt(now()->subMinutes(5)); @endphp
    <div role="status" class="flex flex-wrap items-center gap-2 text-sm rounded-xl px-4 py-3 border
                {{ $cronOk ? 'bg-green-50 border-green-200 text-green-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
        <span class="font-semibold">Scheduler:</span>
        @if(!$schedulerLastRun)
            noch kein Aufruf durch den Server-Cron erfasst.
        @else
            zuletzt aufgerufen {{ $schedulerLastRun->diffForHumans() }}
            ({{ $schedulerLastRun->timezone('Europe/Berlin')->format('d.m.Y H:i') }}).
        @endif
        @unless($cronOk)
            <span>Der Cron muss jede Minute <code class="text-xs">/cron/run/…</code> aufrufen, sonst fallen geplante Aufgaben aus.</span>
        @endunless
    </div>

    {{-- Flash-Nachrichten --}}
    {{-- success/error zeigt das Layout; hier nur das Crawler-Ergebnis. Klassen
         ausgeschrieben, damit der Tailwind-Build sie findet. --}}
    @foreach(['crawler_result'] as $key)
        @if(session($key))
            <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-xl font-medium">
                {{ session($key) }}
            </div>
        @endif
    @endforeach

    {{-- Crawler-Kacheln --}}
    <div>
        <h2 class="text-sm font-bold text-gray-700 mb-2">Crawler-Status & Konfiguration</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">

            @foreach($crawlerStats as $source => $info)
            @php
                $last       = $info['last_entry'];
                $lastStatus = $last?->status;
                $borderCls  = $lastStatus === 'error' ? 'border-red-200' : ($lastStatus === 'success' ? 'border-green-200' : 'border-gray-100');
                $cfgEnabled = $info['cfg_enabled'];
                $cfgDays    = $info['cfg_days'];
                $cfgTime    = $info['cfg_time'];
            @endphp

            <div x-data="{ cfg: false }"
                 class="bg-white rounded-xl shadow-sm border {{ $borderCls }} flex flex-col">

                {{-- Status-Bereich --}}
                <div class="p-4 flex flex-col gap-3 flex-1">

                    {{-- Header --}}
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-bold text-gray-800">{{ $info['label'] }}</p>
                                @if($cfgEnabled)
                                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium">Aktiv</span>
                                @else
                                    <span class="text-xs bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded-full font-medium">Inaktiv</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-600 mt-0.5">{{ $info['schedule'] }}</p>
                        </div>
                        @if($last)
                            @if($lastStatus === 'success')
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Zuletzt OK</span>
                            @elseif($lastStatus === 'error')
                                <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Zuletzt Fehler</span>
                            @else
                                <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Übersprungen</span>
                            @endif
                        @else
                            <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Nie gelaufen</span>
                        @endif
                    </div>

                    {{-- Letzter Lauf --}}
                    @if($last)
                        <div class="text-xs text-gray-500">
                            <span class="font-medium text-gray-700">Letzter Eintrag:</span>
                            {{ $last->imported_at?->format('d.m.Y H:i') ?? '–' }}
                            @if($last->message)
                                <br><span class="text-gray-600 italic">{{ Str::limit($last->message, 120) }}</span>
                            @endif
                        </div>
                    @endif

                    {{-- Statistik --}}
                    <div class="flex gap-3 text-xs">
                        <span class="text-green-700 font-semibold">{{ number_format($info['count_success']) }} importiert</span>
                        <span class="text-gray-600">{{ number_format($info['count_skipped']) }} übersprungen</span>
                        @if($info['count_errors'] > 0)
                            <span class="text-red-600 font-semibold">{{ $info['count_errors'] }} Fehler</span>
                        @endif
                    </div>

                    {{-- Index-URL --}}
                    @if($info['url'])
                        <a href="{{ $info['url'] }}" target="_blank" class="text-xs text-primary hover:underline truncate">
                            {{ $info['url'] }}
                        </a>
                    @endif

                    {{-- Hinweis --}}
                    @if(!empty($info['note']))
                        <p class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1">{{ $info['note'] }}</p>
                    @endif

                    {{-- WebClub: GitHub-Actions-Hinweis --}}
                    @if(!empty($info['is_webclub']))
                        @php $wcToken = \App\Models\Setting::getCached('crawler.webclub.import_token', ''); @endphp
                        @if(!$wcToken)
                            <p class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1">
                                Import-Token fehlt – bitte in der Konfig eintragen und als GitHub Secret <code class="font-mono">WEBCLUB_IMPORT_TOKEN</code> hinterlegen.
                            </p>
                        @else
                            <p class="text-xs text-green-700 bg-green-50 rounded px-2 py-1">
                                Import-Token konfiguriert · Daten kommen via GitHub Actions
                            </p>
                        @endif
                    @endif
                </div>

                {{-- Aktionen --}}
                <div class="px-4 pb-4 flex gap-2">
                    @if(empty($info['note']))
                        @if(!empty($info['is_webclub']))
                            {{-- WebClub läuft via GitHub Actions --}}
                            <a href="https://github.com/mwilkens780/WaRa-Portal/actions/workflows/webclub-crawler.yml"
                               target="_blank"
                               class="bg-primary hover:bg-primary-dark text-white flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                                Auf GitHub starten
                            </a>
                        @else
                            <form method="POST" action="{{ route('admin.import-log.run', $source) }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        data-confirm="Crawler „{{ $info['label'] }}“ jetzt manuell starten?" data-confirm-label="Starten"
                                        class="w-full px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-dark transition-colors">
                                    Jetzt ausführen
                                </button>
                            </form>
                        @endif
                    @endif
                    <button type="button" @click="cfg = !cfg"
                            :class="cfg ? 'bg-gray-100 text-gray-700' : 'text-gray-700 hover:bg-gray-50'"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 transition-colors flex items-center gap-1 flex-shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Konfig
                    </button>
                </div>

                {{-- Konfigurations-Panel --}}
                <div x-show="cfg"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="border-t border-gray-100">

                    @if(!empty($info['is_webclub']))
                    {{-- WebClub: nur Import-Token, alles andere via GitHub Secrets --}}
                    <form method="POST" action="{{ route('admin.import-log.config', $source) }}"
                          class="p-4 space-y-4">
                        @csrf

                        <div class="bg-blue-50 border border-blue-100 rounded-lg px-3 py-2.5">
                            <p class="text-xs font-medium text-blue-800">Zeitplan &amp; Zugangsdaten werden via GitHub Secrets verwaltet</p>
                            <p class="text-xs text-blue-600 mt-1">
                                Secrets: <code class="bg-blue-100 px-0.5 rounded">WEBCLUB_BASE_URL</code>
                                <code class="bg-blue-100 px-0.5 rounded">WEBCLUB_USERNAME</code>
                                <code class="bg-blue-100 px-0.5 rounded">WEBCLUB_PASSWORD</code>
                                <code class="bg-blue-100 px-0.5 rounded">WEBCLUB_IMPORT_TOKEN</code>
                                <code class="bg-blue-100 px-0.5 rounded">PORTAL_URL</code>
                            </p>
                        </div>

                        <div x-data="{ tok: '{{ \App\Models\Setting::getCached('crawler.webclub.import_token', '') }}' }">
                            <label class="text-xs font-medium text-gray-700 block mb-1">
                                Import-Token
                                <span class="text-xs font-normal text-gray-600">→ identisch als GitHub Secret WEBCLUB_IMPORT_TOKEN eintragen</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="text" name="import_token" x-model="tok"
                                       placeholder="Langes zufälliges Token"
                                       class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-mono outline-none focus:ring-2 focus:ring-blue-500">
                                <button type="button"
                                        @click="const c='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'; let t=''; for(let i=0;i<48;i++) t+=c[Math.floor(Math.random()*c.length)]; tok=t"
                                        class="shrink-0 px-2 py-1.5 border border-gray-300 text-xs text-gray-600 rounded-lg hover:bg-gray-50 transition-colors">
                                    Gen.
                                </button>
                            </div>
                        </div>

                        <button type="submit"
                                class="bg-primary hover:bg-primary-dark text-white w-full px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors">
                            Speichern
                        </button>
                    </form>

                    @else
                    {{-- Standard-Crawler: Zeitplan konfigurieren --}}
                    <form method="POST" action="{{ route('admin.import-log.config', $source) }}"
                          class="p-4 space-y-4">
                        @csrf

                        {{-- Aktiv-Toggle --}}
                        <div x-data="{ on: {{ $cfgEnabled ? 'true' : 'false' }} }"
                             class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-700">Automatisch aktiv</span>
                            <div class="flex items-center gap-2">
                                <input type="hidden" name="enabled" :value="on ? '1' : '0'">
                                <button type="button" @click="on = !on"
                                        :class="on ? 'bg-primary' : 'bg-gray-200'"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none">
                                    <span :class="on ? 'translate-x-5' : 'translate-x-1'"
                                          class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm"></span>
                                </button>
                            </div>
                        </div>

                        {{-- Wochentage --}}
                        <div>
                            <p class="text-xs font-medium text-gray-700 mb-1.5">Wochentage</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach([1=>'Mo', 2=>'Di', 3=>'Mi', 4=>'Do', 5=>'Fr', 6=>'Sa', 7=>'So'] as $num => $label)
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" name="schedule_days[]" value="{{ $num }}"
                                               {{ in_array($num, $cfgDays) ? 'checked' : '' }}
                                               class="w-3.5 h-3.5 rounded text-primary border-gray-300">
                                        <span class="text-xs text-gray-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Uhrzeit --}}
                        <div>
                            <label class="text-xs font-medium text-gray-700 block mb-1">Uhrzeit</label>
                            <input aria-label="Uhrzeit" type="time" name="schedule_time" value="{{ $cfgTime }}"
                                   class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-mono outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        {{-- Rückschau in Jahren (nur DSV-Daten) --}}
                        @if(!empty($info['has_states']))
                            <div>
                                <label class="text-xs font-medium text-gray-700 block mb-1">
                                    Rückschau (Jahre)
                                </label>
                                <input aria-label="Rückschau (Jahre)" type="number" name="lookback_years" min="0" max="25"
                                       value="{{ $info['cfg_lookback_years'] ?? 1 }}"
                                       class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-mono outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-600 mt-1 leading-snug">
                                    0 = nur laufendes Jahr, 1 = zusätzlich das Vorjahr.
                                    Jeder Lauf lädt auch bereits importierte Wettkämpfe erneut,
                                    damit gelöschte Ergebnisse zurückkommen — ein hoher Wert
                                    verteuert jeden Lauf. Für einen einmaligen Neuaufbau
                                    hochsetzen, danach wieder senken.
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-medium text-gray-700 mb-1.5">Landesverbände</p>
                                <div class="space-y-1 max-h-40 overflow-y-auto border border-gray-100 rounded-lg p-2">
                                    @foreach($dsvStates as $state)
                                        <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 px-1 py-0.5 rounded">
                                            <input type="checkbox" name="state_ids[]" value="{{ $state['id'] }}"
                                                   {{ in_array($state['id'], $info['cfg_state_ids']) ? 'checked' : '' }}
                                                   class="w-3.5 h-3.5 rounded text-primary border-gray-300">
                                            <span class="text-xs text-gray-700">{{ $state['name'] }}</span>
                                            <span class="text-xs text-gray-600">{{ $state['short'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <button type="submit"
                                class="bg-primary hover:bg-primary-dark text-white w-full px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors">
                            Speichern
                        </button>
                    </form>
                    @endif
                </div>

            </div>
            @endforeach
        </div>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" action="{{ route('admin.import-log.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Quelle</label>
                <select aria-label="Quelle" name="source" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Alle</option>
                    @foreach(['shsv' => 'SHSV', 'nsv' => 'NSV', 'dsvdata' => 'DSV-Daten', 'dsv' => 'DSV National', 'webclub_crawler' => 'WebClub Crawler', 'webclub_batch' => 'WebClub-Batch', 'manual' => 'Manuell'] as $v => $l)
                        <option value="{{ $v }}" {{ ($filters['source'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select aria-label="Status" name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Alle</option>
                    <option value="success" {{ ($filters['status'] ?? '') === 'success' ? 'selected' : '' }}>Erfolg</option>
                    <option value="skipped" {{ ($filters['status'] ?? '') === 'skipped' ? 'selected' : '' }}>Übersprungen</option>
                    <option value="error"   {{ ($filters['status'] ?? '') === 'error'   ? 'selected' : '' }}>Fehler</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Von</label>
                <input aria-label="Von" type="date" name="von" value="{{ $filters['von'] ?? '' }}"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Bis</label>
                <input aria-label="Bis" type="date" name="bis" value="{{ $filters['bis'] ?? '' }}"
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
                <p class="text-sm text-gray-600">Keine Import-Einträge gefunden.</p>
                <p class="text-xs text-gray-600 mt-1">Wenn noch nie Einträge vorhanden waren, haben die Crawler noch nicht gelaufen oder der Cron ist nicht aktiv.</p>
            </div>
        @else
            <x-ui.table :card="false" caption="Importprotokoll" stack
            :columns="['Zeitpunkt', 'Status', 'Quelle', 'Datei / ID', 'Wettkampf', 'Meldung']">
                        @foreach($logs as $log)
                            <tr class="hover:bg-gray-50 {{ $log->isError() ? 'bg-red-50/40' : '' }}">
                                <x-ui.td label="Zeitpunkt" muted class="text-xs whitespace-nowrap">
                                    {{ $log->imported_at?->format('d.m.Y H:i') ?? '–' }}
                                </x-ui.td>
                                <x-ui.td label="Status" class="whitespace-nowrap">
                                    @if($log->isSuccess())
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Erfolg</span>
                                    @elseif($log->isSkipped())
                                        <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full font-medium">Übersprungen</span>
                                    @else
                                        <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">Fehler</span>
                                    @endif
                                </x-ui.td>
                                <x-ui.td label="Quelle" muted class="text-xs whitespace-nowrap">
                                    {{ ['shsv' => 'SHSV', 'nsv' => 'NSV', 'dsvdata' => 'DSV-Daten', 'dsv' => 'DSV National', 'webclub_batch' => 'WebClub-Batch', 'manual' => 'Manuell'][$log->source] ?? $log->source }}
                                </x-ui.td>
                                <x-ui.td label="Datei / ID" class="text-xs max-w-[200px]">
                                    @if($log->source_url)
                                        <a href="{{ $log->source_url }}" target="_blank" class="text-primary hover:underline font-mono break-all">
                                            {{ $log->filename ?? basename($log->source_url) }}
                                        </a>
                                    @else
                                        <span class="text-gray-500 font-mono">{{ $log->filename ?? '–' }}</span>
                                    @endif
                                </x-ui.td>
                                <x-ui.td label="Wettkampf" class="text-xs">
                                    @if($log->competition)
                                        <a href="{{ route('admin.competitions.show', $log->competition) }}" class="text-primary underline underline-offset-2 hover:no-underline">
                                            {{ $log->competition->name }}
                                        </a>
                                    @else
                                        <span class="text-gray-600">–</span>
                                    @endif
                                </x-ui.td>
                                <x-ui.td label="Meldung" muted class="text-xs max-w-xs whitespace-pre-wrap break-words">
                                    {{ $log->message ?? '' }}
                                </x-ui.td>
                            </tr>
                        @endforeach
                    </x-ui.table>

            <div class="px-4 py-3 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
