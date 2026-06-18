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

        {{-- ── DSV-Daten Crawler ─────────────────────────────────────────── --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <div>
                    <h2 class="text-base font-semibold text-gray-800">DSV-Daten Crawler</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Automatischer Import von Ergebnissen von dsvdaten.dsv.de (PDF-Protokolle)</p>
                </div>
            </div>

            <div class="px-6 py-5 space-y-6">

                {{-- Aktiviert --}}
                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Crawler aktiv</p>
                        <p class="text-xs text-gray-500 mt-0.5">Ergebnisse automatisch nach dem konfigurierten Zeitplan holen</p>
                    </div>
                    <div x-data="{ on: {{ $settings['crawler_dsvdata_enabled'] ? 'true' : 'false' }} }" class="flex-shrink-0">
                        <input type="hidden" name="crawler_dsvdata_enabled" :value="on ? '1' : '0'">
                        <button type="button" @click="on = !on"
                                :class="on ? 'bg-blue-500' : 'bg-gray-200'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none">
                            <span :class="on ? 'translate-x-6' : 'translate-x-1'"
                                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow-sm"></span>
                        </button>
                    </div>
                </label>

                {{-- Bundesländer --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bundesländer / Landesverbände</label>
                    <p class="text-xs text-gray-400 mb-3">Welche Bundesländer sollen nach Wettkampfergebnissen durchsucht werden?</p>
                    <div class="border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100">
                        @foreach($dsvStates as $state)
                        <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox"
                                   name="crawler_dsvdata_state_ids[]"
                                   value="{{ $state['id'] }}"
                                   {{ in_array($state['id'], $settings['crawler_dsvdata_state_ids']) ? 'checked' : '' }}
                                   class="w-4 h-4 rounded text-blue-500 border-gray-300">
                            <div class="flex-1">
                                <span class="text-sm font-medium text-gray-700">{{ $state['name'] }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ $state['short'] }} · StateID={{ $state['id'] }}</span>
                            </div>
                        </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        Weitere Bundesländer können durch StateID-Tests auf dsvdaten.dsv.de ermittelt werden.<br>
                        Empfehlung: Zunächst nur norddeutsche Verbände (SHSV, NSV, Hamburg) aktivieren.
                    </p>
                </div>

                {{-- Zeitplan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Zeitplan</label>
                    <div class="space-y-4">

                        {{-- Wochentage --}}
                        <div>
                            <p class="text-xs text-gray-500 mb-2">Wochentage (ISO: 1=Mo, 7=So)</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach([1=>'Mo', 2=>'Di', 3=>'Mi', 4=>'Do', 5=>'Fr', 6=>'Sa', 7=>'So'] as $dayNum => $dayLabel)
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox"
                                           name="crawler_dsvdata_schedule_days[]"
                                           value="{{ $dayNum }}"
                                           {{ in_array($dayNum, $settings['crawler_dsvdata_schedule_days']) ? 'checked' : '' }}
                                           class="w-4 h-4 rounded text-blue-500 border-gray-300">
                                    <span class="text-sm text-gray-700 font-medium">{{ $dayLabel }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Uhrzeit --}}
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Uhrzeit</label>
                            <input type="time"
                                   name="crawler_dsvdata_schedule_time"
                                   value="{{ $settings['crawler_dsvdata_schedule_time'] }}"
                                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 font-mono">
                        </div>

                        <p class="text-xs text-gray-400">
                            Der Crawler läuft automatisch zu den gewählten Zeiten.<br>
                            Zeitplanänderungen werden beim nächsten Scheduler-Lauf (jede Minute) wirksam.
                        </p>
                    </div>
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

</div>
@endsection
