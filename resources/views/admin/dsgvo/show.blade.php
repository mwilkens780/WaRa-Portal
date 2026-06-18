@extends('layouts.app')
@section('title', 'DSGVO-Anfrage #' . $dsgvoRequest->id)
@section('page-title', 'DSGVO-Anfrage')

@section('content')
<div class="mt-2 space-y-5">

    <a href="{{ route('admin.dsgvo.index') }}" class="text-sm text-gray-500 hover:text-gray-700 inline-block">← Zurück zur Übersicht</a>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    {{-- Kopfzeile --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex flex-wrap items-start gap-4">
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <h2 class="text-base font-semibold text-gray-800">
                    {{ $dsgvoRequest->typeLabel() }} – {{ $dsgvoRequest->requester_name }}
                </h2>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $dsgvoRequest->statusColor() }}">
                    {{ $dsgvoRequest->statusLabel() }}
                </span>
                @if($dsgvoRequest->isOverdue())
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-700">⚠️ Frist überschritten!</span>
                @endif
            </div>
            <p class="text-sm text-gray-500">
                Eingegangen: {{ $dsgvoRequest->created_at->format('d.m.Y') }} ·
                Frist: <strong class="{{ $dsgvoRequest->isOverdue() ? 'text-red-600' : '' }}">{{ $dsgvoRequest->deadline()->format('d.m.Y') }}</strong>
                @if($dsgvoRequest->responded_at)
                    · Beantwortet: {{ $dsgvoRequest->responded_at->format('d.m.Y') }}
                @endif
            </p>
            @if($dsgvoRequest->requester_email)
                <p class="text-sm text-gray-500 mt-0.5">E-Mail: <a href="mailto:{{ $dsgvoRequest->requester_email }}" class="text-primary hover:underline">{{ $dsgvoRequest->requester_email }}</a></p>
            @endif
            @if($dsgvoRequest->description)
                <p class="text-sm text-gray-700 mt-3 whitespace-pre-wrap">{{ $dsgvoRequest->description }}</p>
            @endif
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-5">

        {{-- Status-Update --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Bearbeitungsstatus</h3>
                <form action="{{ route('admin.dsgvo.update', $dsgvoRequest) }}" method="POST" class="space-y-3">
                    @csrf @method('PATCH')
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50">
                            @foreach(\App\Models\DsgvoRequest::$statuses as $key => $info)
                                <option value="{{ $key }}" {{ $dsgvoRequest->status === $key ? 'selected' : '' }}>{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Interne Notizen</label>
                        <textarea name="admin_notes" rows="5"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                                  placeholder="Maßnahmen, Kontaktversuche, Begründungen…">{{ old('admin_notes', $dsgvoRequest->admin_notes) }}</textarea>
                    </div>
                    <button type="submit" class="w-full bg-primary text-white rounded-lg py-2 text-sm font-semibold hover:bg-primary-dark transition-colors">
                        Speichern
                    </button>
                </form>
            </div>

            {{-- Anonymisierung --}}
            @if($dsgvoRequest->user)
            <div class="bg-red-50 border border-red-200 rounded-xl p-5"
                 x-data="{ confirm: '', show: false }">
                <h3 class="text-sm font-semibold text-red-800 mb-2">Datenlöschung (Art. 17 DSGVO)</h3>
                <p class="text-xs text-red-700 mb-3">
                    Anonymisiert <strong>{{ $dsgvoRequest->user->name }}</strong> unwiderruflich.
                    Profildaten werden überschrieben, Trainings- und Wettkampfdaten bleiben für Vereinsstatistiken erhalten.
                </p>
                <button type="button" @click="show = true"
                        class="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 transition-colors">
                    Anonymisierung einleiten
                </button>

                <div x-show="show" class="mt-4 space-y-3">
                    <p class="text-xs text-red-700 font-semibold">Zur Bestätigung „LOESCHEN" eingeben:</p>
                    <form action="{{ route('admin.dsgvo.anonymize', $dsgvoRequest->user) }}" method="POST" class="space-y-2">
                        @csrf
                        <input type="text" name="confirm" x-model="confirm" placeholder="LOESCHEN"
                               class="w-full border border-red-300 rounded px-3 py-1.5 text-sm focus:outline-none">
                        <button type="submit"
                                :disabled="confirm !== 'LOESCHEN'"
                                class="w-full bg-red-600 text-white rounded py-1.5 text-xs font-semibold hover:bg-red-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                            Jetzt anonymisieren
                        </button>
                    </form>
                    <button @click="show = false" class="text-xs text-red-500 hover:underline">Abbrechen</button>
                </div>
            </div>
            @endif
        </div>

        {{-- Datenauskunft --}}
        <div class="lg:col-span-2">
            @if($userData)
            <div class="space-y-4">

                {{-- Profil --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Profildaten</h3>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs">
                        @foreach($userData['profil'] as $key => $value)
                        @if($value !== null && $value !== '')
                        <dt class="text-gray-400 font-medium">{{ $key }}</dt>
                        <dd class="text-gray-700 break-all">{{ is_bool($value) ? ($value ? 'ja' : 'nein') : $value }}</dd>
                        @endif
                        @endforeach
                    </dl>
                </div>

                {{-- Trainingsgruppen --}}
                @if(!empty($userData['trainingsgruppen']))
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Trainingsgruppen</h3>
                    <ul class="text-xs text-gray-700 space-y-0.5">
                        @foreach($userData['trainingsgruppen'] as $g)
                            <li>{{ $g['name'] }} (ID {{ $g['id'] }})</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- Trainingsanwesenheit --}}
                @if(!empty($userData['trainings_anwesenheit']))
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">
                        Trainingsanwesenheit ({{ count($userData['trainings_anwesenheit']) }} Einträge)
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left px-2 py-1 text-gray-500">Datum</th>
                                    <th class="text-left px-2 py-1 text-gray-500">Einheit</th>
                                    <th class="text-left px-2 py-1 text-gray-500">Anwesend</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach(array_slice($userData['trainings_anwesenheit'], 0, 50) as $a)
                                <tr>
                                    <td class="px-2 py-1 text-gray-500">{{ isset($a['session']) ? \Carbon\Carbon::parse($a['session']['date'])->format('d.m.Y') : '—' }}</td>
                                    <td class="px-2 py-1 text-gray-700">{{ $a['session']['title'] ?? '—' }}</td>
                                    <td class="px-2 py-1">{{ $a['attended'] ? '✓' : '✗' }}</td>
                                </tr>
                                @endforeach
                                @if(count($userData['trainings_anwesenheit']) > 50)
                                    <tr><td colspan="3" class="px-2 py-1 text-gray-400 italic">… {{ count($userData['trainings_anwesenheit']) - 50 }} weitere Einträge nicht angezeigt</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Wettkampfergebnisse --}}
                @if(!empty($userData['wettkampf_ergebnisse']))
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">
                        Wettkampfergebnisse ({{ count($userData['wettkampf_ergebnisse']) }} Einträge)
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left px-2 py-1 text-gray-500">Wettkampf</th>
                                    <th class="text-left px-2 py-1 text-gray-500">Disziplin</th>
                                    <th class="text-left px-2 py-1 text-gray-500">Zeit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach(array_slice($userData['wettkampf_ergebnisse'], 0, 50) as $r)
                                <tr>
                                    <td class="px-2 py-1 text-gray-500">{{ $r['competition']['name'] ?? '—' }}</td>
                                    <td class="px-2 py-1 text-gray-700">{{ $r['discipline'] ?? '' }} {{ $r['distance'] ?? '' }}m</td>
                                    <td class="px-2 py-1">{{ $r['time_ms'] ? number_format($r['time_ms'] / 1000, 2) . 's' : '—' }}</td>
                                </tr>
                                @endforeach
                                @if(count($userData['wettkampf_ergebnisse']) > 50)
                                    <tr><td colspan="3" class="px-2 py-1 text-gray-400 italic">… {{ count($userData['wettkampf_ergebnisse']) - 50 }} weitere Einträge nicht angezeigt</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Support-Tickets --}}
                @if(!empty($userData['support_tickets']))
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Support-Tickets ({{ count($userData['support_tickets']) }})</h3>
                    <ul class="text-xs text-gray-700 space-y-0.5">
                        @foreach($userData['support_tickets'] as $t)
                            <li>{{ $t['type'] }}: {{ $t['title'] }} (GitHub #{{ $t['github_issue_number'] ?? '—' }}, {{ \Carbon\Carbon::parse($t['created_at'])->format('d.m.Y') }})</li>
                        @endforeach
                    </ul>
                </div>
                @endif

            </div>
            @else
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-8 text-center text-sm text-gray-400">
                @if($dsgvoRequest->user_id)
                    Nutzer konnte nicht gefunden werden (evtl. bereits anonymisiert).
                @else
                    Kein Portal-Nutzer zugeordnet. Für eine vollständige Datenauskunft muss die Anfrage einem Nutzer zugeordnet sein.
                @endif
            </div>
            @endif
        </div>

    </div>
</div>
@endsection
