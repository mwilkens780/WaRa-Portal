@extends('layouts.app')
@section('title', 'Einstellungen')
@section('page-title', 'Einstellungen')

@section('content')
<div class="max-w-2xl mt-2 space-y-6">

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        {{-- Wartungsmodus --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <h2 class="text-base font-semibold text-gray-800">Wartungsmodus</h2>
            </div>

            <div class="px-6 py-5 space-y-5">

                {{-- Toggle --}}
                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Wartungsmodus aktivieren</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Nicht freigegebene Benutzer sehen die Wartungsmeldung. Alle System-E-Mails
                            werden an <span class="font-medium">administrator@wara-portal.de</span> umgeleitet.
                        </p>
                    </div>
                    <div x-data="{ on: {{ $settings['maintenance_mode'] ? 'true' : 'false' }} }" class="flex-shrink-0">
                        <input type="hidden" name="maintenance_mode" :value="on ? '1' : '0'">
                        <button type="button" @click="on = !on"
                                :class="on ? 'bg-amber-500' : 'bg-gray-200'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                            <span :class="on ? 'translate-x-6' : 'translate-x-1'"
                                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm"></span>
                        </button>
                    </div>
                </label>

                {{-- Meldungstext --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Wartungsmeldung</label>
                    <textarea name="maintenance_message" rows="3"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                              placeholder="Wird den Benutzern angezeigt…">{{ old('maintenance_message', $settings['maintenance_message']) }}</textarea>
                </div>

                {{-- Bypass-Benutzer --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Freigegebene Benutzer
                        <span class="text-xs text-gray-400 font-normal ml-1">(können sich auch im Wartungsmodus einloggen)</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-3">Admins haben immer Zugriff und müssen nicht ausgewählt werden.</p>

                    @if($users->isEmpty())
                        <p class="text-sm text-gray-400 italic">Keine aktiven Nicht-Admin-Benutzer vorhanden.</p>
                    @else
                        <div class="border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 max-h-64 overflow-y-auto">
                            @foreach($users as $user)
                            <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox"
                                       name="maintenance_bypass_users[]"
                                       value="{{ $user->id }}"
                                       {{ in_array($user->id, $settings['maintenance_bypass_users']) ? 'checked' : '' }}
                                       class="w-4 h-4 rounded text-primary border-gray-300">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-700">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $user->role_label }}{{ $user->email ? ' · ' . $user->email : '' }}</p>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                Einstellungen speichern
            </button>
        </div>
    </form>

    {{-- WebClub: Hinweis, Konfig jetzt direkt in der Crawler-Seite --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
            <div>
                <h2 class="text-base font-semibold text-gray-800">WebClub-Schnittstelle</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    WebClub läuft via GitHub Actions. Import-Token und Status findest du unter
                    <a href="{{ route('admin.import-log.index') }}" class="text-primary font-medium hover:underline">Crawler &amp; Import-Log → Konfig</a>.
                </p>
            </div>
        </div>
    </div>

    {{-- ENTFERNT (jetzt in Crawler-Konfig): frühere WebClub-Form --}}
    @if(false)
    <form method="POST" action="{{ route('admin.settings.webclub') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                <div>
                    <h2 class="text-base font-semibold text-gray-800">WebClub-Schnittstelle</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Playwright-Crawler für web-club.app · Zeitplan im <a href="{{ route('admin.import-log.index') }}" class="text-primary hover:underline">Import-Log</a> konfigurierbar</p>
                </div>
            </div>

            <div class="px-6 py-5 space-y-5">

                @if(session('webclub_success'))
                    <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">
                        {{ session('webclub_success') }}
                    </div>
                @endif
                @if($errors->hasBag('webclub'))
                    <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">
                        @foreach($errors->getBag('webclub')->all() as $err)
                            <p>{{ $err }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- Aktiviert --}}
                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Crawler aktiviert</p>
                        <p class="text-xs text-gray-500 mt-0.5">Wenn deaktiviert, wird der Scheduler-Auftrag übersprungen.</p>
                    </div>
                    <div x-data="{ on: {{ $webclub['enabled'] ? 'true' : 'false' }} }" class="flex-shrink-0">
                        <input type="hidden" name="webclub_enabled" :value="on ? '1' : '0'">
                        <button type="button" @click="on = !on"
                                :class="on ? 'bg-blue-500' : 'bg-gray-200'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                            <span :class="on ? 'translate-x-6' : 'translate-x-1'"
                                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm"></span>
                        </button>
                    </div>
                </label>

                {{-- Basis-URL --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        WebClub-URL
                        <span class="text-xs text-gray-400 font-normal ml-1">z.B. https://meinverein.web-club.app</span>
                    </label>
                    <input type="url" name="webclub_base_url"
                           value="{{ old('webclub_base_url', $webclub['base_url']) }}"
                           placeholder="https://…"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none font-mono">
                </div>

                {{-- Benutzername --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Benutzername oder E-Mail</label>
                    <input type="text" name="webclub_username"
                           value="{{ old('webclub_username', $webclub['username']) }}"
                           placeholder="benutzername oder name@example.de"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                {{-- Passwort --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Passwort
                        <span class="text-xs text-gray-400 font-normal ml-1">
                            {{ $webclub['password_set'] ? '(gespeichert – Feld leer lassen, um es beizubehalten)' : '(noch nicht gesetzt)' }}
                        </span>
                    </label>
                    <input type="password" name="webclub_password"
                           placeholder="{{ $webclub['password_set'] ? '••••••••' : 'Passwort eingeben…' }}"
                           autocomplete="new-password"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                {{-- Was synchronisieren --}}
                <div>
                    <p class="text-sm font-medium text-gray-700 mb-2">Synchronisieren</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="webclub_scrape_competitions" value="1"
                                   {{ $webclub['scrape_competitions'] ? 'checked' : '' }}
                                   class="w-4 h-4 rounded text-primary border-gray-300">
                            <span class="text-sm text-gray-700">Veranstaltungen (Organisation, Meldungen, Ergebnisse)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="webclub_scrape_persons" value="1"
                                   {{ $webclub['scrape_persons'] ? 'checked' : '' }}
                                   class="w-4 h-4 rounded text-primary border-gray-300">
                            <span class="text-sm text-gray-700">Personen (Mitglieder-Daten ergänzen)</span>
                        </label>
                    </div>
                </div>

                {{-- Datumsbereich --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Rückblick (Tage)
                            <span class="text-xs text-gray-400 font-normal ml-1">vergangene Veranstaltungen</span>
                        </label>
                        <input type="number" name="webclub_lookback_days" min="0" max="3650"
                               value="{{ old('webclub_lookback_days', $webclub['lookback_days']) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        <p class="text-xs text-amber-600 mt-1">Gilt nur für manuellen Artisan-Befehl. Für den GitHub-Actions-Crawler: beim manuellen Starten im Workflow-Feld angeben.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Vorschau (Tage)
                            <span class="text-xs text-gray-400 font-normal ml-1">zukünftige Veranstaltungen</span>
                        </label>
                        <input type="number" name="webclub_lookahead_days" min="0" max="730"
                               value="{{ old('webclub_lookahead_days', $webclub['lookahead_days']) }}"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                </div>

                {{-- Erweitert --}}
                <div x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                            class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                        <svg :class="open ? 'rotate-90' : ''" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        Erweiterte Optionen
                    </button>
                    <div x-show="open" class="mt-3 space-y-4 pl-4 border-l-2 border-gray-100">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="webclub_headless" value="1"
                                   {{ $webclub['headless'] ? 'checked' : '' }}
                                   class="w-4 h-4 rounded text-primary border-gray-300">
                            <span class="text-sm text-gray-700">Headless-Modus (kein Browser-Fenster)</span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Selektor-Timeout (ms)
                                <span class="text-xs text-gray-400 font-normal ml-1">Standard: 15000</span>
                            </label>
                            <input type="number" name="webclub_timeout_ms" min="5000" max="60000" step="1000"
                                   value="{{ old('webclub_timeout_ms', $webclub['timeout_ms']) }}"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prozess-Timeout (Sekunden)</label>
                            <input type="number" name="webclub_timeout_seconds" min="60" max="1800"
                                   value="{{ old('webclub_timeout_seconds', $webclub['timeout_seconds']) }}"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Node.js-Pfad
                                <span class="text-xs text-gray-400 font-normal ml-1">leer lassen = automatisch suchen</span>
                            </label>
                            <input type="text" name="webclub_node_path"
                                   value="{{ old('webclub_node_path', $webclub['node_path']) }}"
                                   placeholder="/usr/bin/node"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none font-mono">
                            <p class="text-xs text-gray-400 mt-1">Pfad zur Node.js-Executable auf dem Server. Per SSH ermitteln: <code class="bg-gray-100 px-1 rounded">which node</code></p>
                        </div>
                    </div>
                </div>

                {{-- GitHub Actions Import-Token --}}
                <div class="border border-blue-100 bg-blue-50 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-blue-800">GitHub Actions Import-Token</p>
                            <p class="text-xs text-blue-600 mt-0.5">
                                Der Crawler läuft als GitHub Actions Workflow und sendet die Ergebnisse per API an dieses Portal.
                                Das Token muss in GitHub als Secret <code class="bg-blue-100 px-1 rounded">WEBCLUB_IMPORT_TOKEN</code> hinterlegt werden.
                            </p>
                            <div class="mt-3 flex gap-2 items-end">
                                <div class="flex-1">
                                    <input type="text" name="webclub_import_token"
                                           value="{{ old('webclub_import_token', $webclub['import_token'] ?? '') }}"
                                           placeholder="Langes zufälliges Token, z.B. aus: openssl rand -hex 32"
                                           class="w-full px-4 py-2.5 border border-blue-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none font-mono bg-white">
                                </div>
                                <button type="button" onclick="
                                    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                                    let token = '';
                                    for (let i = 0; i < 48; i++) token += chars[Math.floor(Math.random() * chars.length)];
                                    this.closest('.flex').querySelector('input').value = token;
                                " class="shrink-0 px-3 py-2.5 border border-blue-200 bg-white text-xs text-blue-700 rounded-lg hover:bg-blue-100 transition-colors font-medium">
                                    Generieren
                                </button>
                            </div>
                            <p class="text-xs text-blue-500 mt-1.5">
                                GitHub Secrets: <strong>WEBCLUB_BASE_URL</strong>, <strong>WEBCLUB_USERNAME</strong>, <strong>WEBCLUB_PASSWORD</strong>, <strong>WEBCLUB_IMPORT_TOKEN</strong>, <strong>PORTAL_URL</strong>
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                WebClub-Einstellungen speichern
            </button>
        </div>
    </form>
    @endif {{-- Ende @if(false) --}}

</div>
@endsection
