{{-- Kampfgericht auf dem Dashboard – Daten: App\View\Components\OfficialsPanel --}}
@php
    use App\Models\CompetitionOfficialRequest;
    $isAdmin = auth()->user()?->hasRole('admin');
@endphp

<div class="space-y-4">
    {{-- Erinnerungen für Kampfrichter --}}
    @if($openRequests->isNotEmpty() || $ownExpiring->isNotEmpty())
        <x-ui.alert tone="warning">
            <ul class="space-y-1">
                @foreach($openRequests as $inv)
                    <li>
                        Kampfrichter gesucht für <strong>{{ $inv->request->competition->name }}</strong>
                        ({{ $inv->request->competition->date->format('d.m.Y') }}){{ $inv->request->deadline ? ' – Rückmeldung bis ' . $inv->request->deadline->format('d.m.Y') : '' }}.
                        <a href="{{ route('officials.respond', $inv->request) }}" class="font-semibold underline">Jetzt antworten</a>
                    </li>
                @endforeach
                @foreach($ownExpiring as $q)
                    <li>
                        Deine Qualifikation <strong>{{ $q->title }}</strong>{{ $q->license_nr ? ' (Lizenz ' . $q->license_nr . ')' : '' }}
                        {{ $q->valid_until->isPast() ? 'ist am' : 'läuft am' }} <strong>{{ $q->valid_until->format('d.m.Y') }}</strong>
                        {{ $q->valid_until->isPast() ? 'abgelaufen' : 'ab' }}. Bitte rechtzeitig verlängern –
                        <a href="{{ route('officials.mine') }}" class="font-semibold underline">Qualifikationen pflegen</a>
                    </li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="grid gap-4 {{ ($isBoard || $managesLicenses) && $isOfficial ? 'lg:grid-cols-2' : '' }}">
        {{-- Kampfrichter: anstehend und letzte Einsätze --}}
        @if($isOfficial)
            <x-ui.card title="Meine Kampfrichter-Einsätze">
                @if($upcoming->isEmpty() && $lastAssignments->isEmpty())
                    <p class="text-sm text-gray-600">Keine Anfragen für anstehende Wettkämpfe.</p>
                @endif
                @if($upcoming->isNotEmpty())
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-2">Anstehend</h3>
                    <ul class="divide-y divide-gray-100 mb-4">
                        @foreach($upcoming as $inv)
                            @php $c = $inv->request->competition; @endphp
                            <li class="py-2 text-sm">
                                <a href="{{ route('officials.respond', $inv->request) }}" class="font-medium text-gray-900 hover:text-primary">{{ $c->name }}</a>
                                <span class="block text-gray-600">
                                    {{ $c->date->format('d.m.Y') }}{{ $c->location ? ' · ' . $c->location : '' }} ·
                                    @if($inv->request->finalized_at && $inv->assignments->isNotEmpty())
                                        <span class="font-medium text-green-800">eingesetzt:</span>
                                        {{ $inv->assignments->map(fn($a) => 'Abschnitt ' . $a->session_number . ' ' . $a->position_label)->implode(', ') }}
                                    @elseif($inv->request->finalized_at)
                                        nicht eingesetzt
                                    @elseif($inv->responded_at)
                                        {{ $inv->availableAnyDay() ? 'zugesagt, Einteilung folgt' : 'abgesagt' }}
                                    @else
                                        <span class="font-medium text-amber-800">Rückmeldung offen</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($lastAssignments->isNotEmpty())
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-2">Letzte Einsätze</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach($lastAssignments as $inv)
                            <li class="py-2 text-sm">
                                <span class="font-medium text-gray-900">{{ $inv->request->competition->name }}</span>
                                <span class="block text-gray-600">{{ $inv->request->competition->date->format('d.m.Y') }} ·
                                    {{ $inv->assignments->map(fn($a) => $a->position_label)->unique()->implode(', ') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif

        {{-- Vorstand: Kampfrichter-Meldungen und Lizenzen --}}
        @if($isBoard)
            <x-ui.card title="Kampfgericht – anstehende Wettkämpfe">
                @if($boardCompetitions->isEmpty())
                    <p class="text-sm text-gray-600">Keine Wettkämpfe in den nächsten 90 Tagen.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach($boardCompetitions as $c)
                            @php
                                $r   = $c->officialRequest;
                                $inv = $r?->invitees ?? collect();
                                [$label, $tone] = match (true) {
                                    !$r                         => ['keine Abfrage', 'neutral'],
                                    (bool) $r->finalized_at     => ['gemeldet (' . $inv->filter(fn($i) => $i->assignments->isNotEmpty())->count() . ')', 'success'],
                                    $r->needs->isNotEmpty() && $r->readyToAssign() => ($o = $r->openCount()) ? [$o . ' Position' . ($o === 1 ? '' : 'en') . ' offen', 'warning'] : ['besetzt, Freigabe fehlt', 'info'],
                                    $inv->flatMap->assignments->isNotEmpty() => ['Zuordnung begonnen', 'info'],
                                    default                     => [$inv->whereNotNull('responded_at')->count() . '/' . $inv->count() . ' Rückmeldungen', 'warning'],
                                };
                            @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <a href="{{ route('admin.competitions.show', $c) }}?tab=kampfgericht" class="min-w-0 font-medium text-gray-900 hover:text-primary">
                                    {{ $c->name }}<span class="block font-normal text-gray-600">{{ $c->date->format('d.m.Y') }}{{ $c->location ? ' · ' . $c->location : '' }}</span>
                                </a>
                                <x-ui.badge :tone="$tone">{{ $label }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

        @endif

        @if($managesLicenses)
            <x-ui.card title="Auslaufende Kampfrichter-Lizenzen" :meta="'nächste ' . \App\View\Components\OfficialsPanel::LICENSE_WARN_MONTHS . ' Monate'">
                @if($expiringLicenses->isEmpty())
                    <p class="text-sm text-gray-600">Keine Lizenz läuft in den nächsten {{ \App\View\Components\OfficialsPanel::LICENSE_WARN_MONTHS }} Monaten aus.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach($expiringLicenses as $q)
                            @php $d = $q->valid_until; $u = $q->user; @endphp
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <span class="min-w-0">
                                    <a href="{{ route('officials.index', ['auslaufend' => 1]) }}" class="font-medium text-gray-900 hover:text-primary">{{ $u->lastname }}, {{ $u->firstname }}</a>
                                    <span class="block text-gray-600">{{ $q->title }}{{ $q->license_nr ? ' · Lizenz ' . $q->license_nr : '' }}</span>
                                </span>
                                <x-ui.badge :tone="$d->isPast() ? 'danger' : ($d->lte(today()->addMonths(2)) ? 'warning' : 'neutral')">
                                    {{ $d->isPast() ? 'abgelaufen' : 'bis' }} {{ $d->format('d.m.Y') }}
                                </x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif
    </div>
</div>
