@extends('layouts.app')
@section('title', 'Import-Log & Crawler')
@section('page-title', 'Import-Log & Crawler')

@section('content')
<div class="space-y-5">

    {{-- Flash-Nachrichten --}}
    @foreach(['success' => 'green', 'crawler_result' => 'green', 'error' => 'red'] as $key => $color)
        @if(session($key))
            <div class="bg-{{ $color }}-50 border border-{{ $color }}-200 text-{{ $color }}-800 text-sm px-4 py-3 rounded-xl font-medium">
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
                                    <span class="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium">Aktiv</span>
                                @else
                                    <span class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full font-medium">Inaktiv</span>
                                @endif
                            </div>
                            <p class="text-[10px] text-gray-400 mt-0.5">{{ $info['schedule'] }}</p>
                        </div>
                        @if($last)
                            @if($lastStatus === 'success')
                                <span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Zuletzt OK</span>
                            @elseif($lastStatus === 'error')
                                <span class="text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Zuletzt Fehler</span>
                            @else
                                <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Übersprungen</span>
                            @endif
                        @else
                            <span class="text-[10px] bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Nie gelaufen</span>
                        @endif
                    </div>

                    {{-- Letzter Lauf --}}
                    @if($last)
                        <div class="text-xs text-gray-500">
                            <span class="font-medium text-gray-700">Letzter Eintrag:</span>
                            {{ $last->imported_at?->format('d.m.Y H:i') ?? '–' }}
                            @if($last->message)
                                <br><span class="text-gray-400 italic">{{ Str::limit($last->message, 65) }}</span>
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

                    {{-- WebClub: Konfigurations-Status --}}
                    @if(!empty($info['is_webclub']))
                        @if(empty($info['configured']))
                            <p class="text-[10px] text-amber-600 bg-amber-50 rounded px-2 py-1">
                                Zugangsdaten fehlen –
                                <a href="{{ route('admin.settings.index') }}#webclub" class="underline font-semibold">Einstellungen</a>
                            </p>
                        @else
                            <p class="text-[10px] text-gray-500">
                                Benutzer: {{ $info['username'] ?? '–' }} ·
                                <a href="{{ route('admin.settings.index') }}#webclub" class="text-primary hover:underline">Einstellungen</a>
                            </p>
                        @endif
                    @endif
                </div>

                {{-- Aktionen --}}
                <div class="px-4 pb-4 flex gap-2">
                    @if(empty($info['note']))
                        <form method="POST" action="{{ route('admin.import-log.run', $source) }}" class="flex-1">
                            @csrf
                            <button type="submit"
                                    onclick="return confirm('Crawler \"{{ $info['label'] }}\" jetzt manuell starten?')"
                                    @if(!empty($info['is_webclub']) && empty($info['configured'])) disabled @endif
                                    class="w-full px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary-dark transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                Jetzt ausführen
                            </button>
                        </form>
                    @endif
                    <button type="button" @click="cfg = !cfg"
                            :class="cfg ? 'bg-gray-100 text-gray-700' : 'text-gray-500 hover:bg-gray-50'"
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
                            <input type="time" name="schedule_time" value="{{ $cfgTime }}"
                                   class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-mono outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        {{-- Landesverbände (nur DSV-Daten) --}}
                        @if(!empty($info['has_states']))
                            <div>
                                <p class="text-xs font-medium text-gray-700 mb-1.5">Landesverbände</p>
                                <div class="space-y-1 max-h-40 overflow-y-auto border border-gray-100 rounded-lg p-2">
                                    @foreach($dsvStates as $state)
                                        <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 px-1 py-0.5 rounded">
                                            <input type="checkbox" name="state_ids[]" value="{{ $state['id'] }}"
                                                   {{ in_array($state['id'], $info['cfg_state_ids']) ? 'checked' : '' }}
                                                   class="w-3.5 h-3.5 rounded text-primary border-gray-300">
                                            <span class="text-xs text-gray-700">{{ $state['name'] }}</span>
                                            <span class="text-[10px] text-gray-400">{{ $state['short'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <button type="submit"
                                class="w-full px-3 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-lg hover:bg-gray-700 transition-colors">
                            Speichern
                        </button>
                    </form>
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
                <select name="source" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Alle</option>
                    @foreach(['shsv' => 'SHSV', 'nsv' => 'NSV', 'dsvdata' => 'DSV-Daten', 'dsv' => 'DSV National', 'webclub_crawler' => 'WebClub Crawler', 'webclub_batch' => 'WebClub-Batch', 'manual' => 'Manuell'] as $v => $l)
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
