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

</div>
@endsection
