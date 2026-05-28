@extends('layouts.app')
@section('title', 'Protokoll')
@section('page-title', 'Protokoll')

@section('content')
<div x-data="{ tab: '{{ request()->filled('tr_level') || request()->filled('tr_from') || request()->filled('tr_to') || request()->query('tab') === 'traces' ? 'traces' : (request()->query('tab') === 'settings' ? 'settings' : 'transactions') }}' }"
     class="space-y-4">

    {{-- Tab bar --}}
    <div class="flex gap-1 bg-white rounded-xl shadow-sm border border-gray-100 p-1.5 w-fit">
        <button @click="tab='transactions'"
                :class="tab==='transactions' ? 'bg-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Transaktionen
            <span class="text-xs bg-white/20 px-1.5 py-0.5 rounded">{{ $transactions->total() }}</span>
        </button>
        <button @click="tab='traces'"
                :class="tab==='traces' ? 'bg-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Traces
            <span class="text-xs bg-white/20 px-1.5 py-0.5 rounded">{{ $traces->total() }}</span>
        </button>
        <button @click="tab='settings'"
                :class="tab==='settings' ? 'bg-primary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Einstellungen
        </button>
    </div>

    {{-- ══ TRANSACTIONS TAB ══════════════════════════════════════════════ --}}
    <div x-show="tab==='transactions'" x-cloak>

        {{-- Filter --}}
        <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <input type="hidden" name="tab" value="transactions">
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Aktion</label>
                    <select name="tx_action" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Alle</option>
                        <option value="created" {{ request('tx_action') === 'created' ? 'selected' : '' }}>Erstellt</option>
                        <option value="updated" {{ request('tx_action') === 'updated' ? 'selected' : '' }}>Geändert</option>
                        <option value="deleted" {{ request('tx_action') === 'deleted' ? 'selected' : '' }}>Gelöscht</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Objekt-Typ</label>
                    <select name="tx_model" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Alle</option>
                        @foreach($modelTypes as $mt)
                            <option value="{{ $mt }}" {{ request('tx_model') === $mt ? 'selected' : '' }}>{{ $mt }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Benutzer</label>
                    <input type="text" name="tx_user" value="{{ request('tx_user') }}" placeholder="Name..."
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 w-36">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Von</label>
                    <input type="date" name="tx_from" value="{{ request('tx_from') }}"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bis</label>
                    <input type="date" name="tx_to" value="{{ request('tx_to') }}"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">Filtern</button>
                @if(request()->hasAny(['tx_action','tx_model','tx_user','tx_from','tx_to']))
                    <a href="{{ route('admin.logs.index', ['tab'=>'transactions']) }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">Zurücksetzen</a>
                @endif
            </div>
        </form>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold">Zeitpunkt</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Benutzer</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Aktion</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Objekt</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Änderungen</th>
                            <th class="px-4 py-2.5 text-left font-semibold hidden lg:table-cell">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($transactions as $tx)
                            <tr class="hover:bg-gray-50 transition-colors" x-data="{ open: false }">
                                <td class="px-4 py-2.5 text-gray-500 text-xs whitespace-nowrap">
                                    {{ $tx->created_at->format('d.m.Y H:i:s') }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-700 text-xs">{{ $tx->user_name }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $tx->action_color }}">
                                        {{ $tx->action_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-xs">
                                    <span class="font-medium text-gray-700">{{ $tx->model_type }}</span>
                                    <span class="text-gray-400"> #{{ $tx->model_id }}</span>
                                    @if($tx->model_label)
                                        <br><span class="text-gray-500">{{ $tx->model_label }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs max-w-xs">
                                    @if($tx->changes)
                                        <button @click="open=!open" class="text-blue-600 hover:text-blue-800 underline text-xs">
                                            <span x-text="open ? 'Ausblenden' : 'Details'">Details</span>
                                        </button>
                                        <div x-show="open" x-cloak class="mt-1.5 font-mono bg-gray-50 rounded p-2 space-y-0.5 max-h-48 overflow-y-auto border border-gray-200">
                                            @if(isset($tx->changes['after']))
                                                @foreach($tx->changes['after'] as $field => $newVal)
                                                    <div class="flex gap-1.5 items-baseline">
                                                        <span class="text-gray-500 w-28 flex-shrink-0">{{ $field }}</span>
                                                        @if(isset($tx->changes['before'][$field]))
                                                            <span class="line-through text-red-400">{{ Str::limit((string) $tx->changes['before'][$field], 40) }}</span>
                                                            <span class="text-gray-400">→</span>
                                                        @endif
                                                        <span class="text-green-700">{{ Str::limit((string) $newVal, 60) }}</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-gray-400 text-xs hidden lg:table-cell">{{ $tx->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Keine Einträge gefunden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($transactions->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $transactions->links() }}</div>
            @endif
        </div>

        {{-- Clear --}}
        <form method="POST" action="{{ route('admin.logs.transactions.clear') }}"
              onsubmit="return confirm('Einträge wirklich löschen?')"
              class="flex items-center gap-3 mt-3">
            @csrf @method('DELETE')
            <label class="text-sm text-gray-600">Einträge älter als</label>
            <input type="date" name="before" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="px-4 py-1.5 bg-red-50 border border-red-200 text-red-700 hover:bg-red-100 rounded-lg text-sm font-medium transition-colors">
                Löschen
            </button>
            <span class="text-xs text-gray-400">Datum leer lassen = alle Einträge löschen</span>
        </form>
    </div>

    {{-- ══ TRACES TAB ════════════════════════════════════════════════════ --}}
    <div x-show="tab==='traces'" x-cloak>

        {{-- Filter --}}
        <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <input type="hidden" name="tab" value="traces">
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Level</label>
                    <select name="tr_level" class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Alle</option>
                        <option value="1" {{ request('tr_level') === '1' ? 'selected' : '' }}>Fehler</option>
                        <option value="2" {{ request('tr_level') === '2' ? 'selected' : '' }}>Warnungen</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Von</label>
                    <input type="date" name="tr_from" value="{{ request('tr_from') }}"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bis</label>
                    <input type="date" name="tr_to" value="{{ request('tr_to') }}"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">Filtern</button>
                @if(request()->hasAny(['tr_level','tr_from','tr_to']))
                    <a href="{{ route('admin.logs.index', ['tab'=>'traces']) }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition-colors">Zurücksetzen</a>
                @endif
            </div>
        </form>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold">Zeitpunkt</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Level</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Meldung</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Kontext</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($traces as $trace)
                            <tr class="hover:bg-gray-50 transition-colors" x-data="{ open: false }">
                                <td class="px-4 py-2.5 text-gray-500 text-xs whitespace-nowrap">
                                    {{ $trace->created_at->format('d.m.Y H:i:s') }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $trace->level_color }}">
                                        {{ $trace->level_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-gray-700 text-xs max-w-sm">{{ $trace->message }}</td>
                                <td class="px-4 py-2.5 text-xs">
                                    @if($trace->context)
                                        <button @click="open=!open" class="text-blue-600 hover:text-blue-800 underline text-xs">
                                            <span x-text="open ? 'Ausblenden' : 'Details'">Details</span>
                                        </button>
                                        <div x-show="open" x-cloak class="mt-1.5 font-mono bg-gray-50 rounded p-2 space-y-0.5 border border-gray-200 max-w-md">
                                            @foreach($trace->context as $key => $val)
                                                <div class="flex gap-1.5">
                                                    <span class="text-gray-500 w-20 flex-shrink-0">{{ $key }}</span>
                                                    <span class="text-gray-700 break-all">{{ Str::limit((string) $val, 120) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Keine Trace-Einträge gefunden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($traces->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $traces->links() }}</div>
            @endif
        </div>

        {{-- Clear --}}
        <form method="POST" action="{{ route('admin.logs.traces.clear') }}"
              onsubmit="return confirm('Trace-Einträge wirklich löschen?')"
              class="flex items-center gap-3 mt-3">
            @csrf @method('DELETE')
            <label class="text-sm text-gray-600">Einträge älter als</label>
            <input type="date" name="before" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" class="px-4 py-1.5 bg-red-50 border border-red-200 text-red-700 hover:bg-red-100 rounded-lg text-sm font-medium transition-colors">
                Löschen
            </button>
            <span class="text-xs text-gray-400">Datum leer lassen = alle Einträge löschen</span>
        </form>
    </div>

    {{-- ══ SETTINGS TAB ══════════════════════════════════════════════════ --}}
    <div x-show="tab==='settings'" x-cloak>
        <div class="max-w-xl">
            <form method="POST" action="{{ route('admin.logs.settings') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
                @csrf

                {{-- Trace level --}}
                <div>
                    <p class="text-sm font-semibold text-gray-800 mb-1">Trace-Level</p>
                    <p class="text-xs text-gray-500 mb-3">Bestimmt, welche Ereignisse in den Fehler-/Trace-Log geschrieben werden.</p>
                    <div class="space-y-2">
                        @php
                            $levels = [
                                0 => ['label' => 'Keine Traces', 'desc' => 'Nichts wird geloggt.', 'color' => 'gray'],
                                1 => ['label' => 'Nur Fehler',   'desc' => 'Unbehandelte Ausnahmen und kritische Fehler.', 'color' => 'red'],
                                2 => ['label' => 'Fehler + Warnungen', 'desc' => 'Fehler und Warnungen (z.B. fehlgeschlagene Import-Zeilen).', 'color' => 'amber'],
                            ];
                        @endphp
                        @foreach($levels as $val => $info)
                            <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer
                                   {{ $traceLevel === $val ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-gray-50' }}">
                                <input type="radio" name="trace_level" value="{{ $val }}"
                                       {{ $traceLevel === $val ? 'checked' : '' }}
                                       class="mt-0.5 text-primary">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $info['label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $info['desc'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Transaction log toggle --}}
                <div class="flex items-start gap-4 pt-2 border-t border-gray-100">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800">Transaktionslog</p>
                        <p class="text-xs text-gray-500 mt-0.5">Schreibt für jede Datenbankänderung (Erstellen, Ändern, Löschen) einen Eintrag.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer mt-1">
                        <input type="hidden" name="transaction_log_enabled" value="0">
                        <input type="checkbox" name="transaction_log_enabled" value="1"
                               {{ $transactionLogEnabled ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-primary rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <button type="submit"
                            class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
                        Einstellungen speichern
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Persist active tab to query string on tab click
document.addEventListener('alpine:init', () => {
    // handled via x-data above
});
</script>
@endpush
