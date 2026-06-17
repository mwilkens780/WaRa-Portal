@extends('layouts.app')
@section('title', 'Meine Wettkämpfe')
@section('page-title', 'Meine Wettkämpfe')

@section('content')
<div class="mt-2 space-y-4">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl px-4 py-3">{{ session('error') }}</div>
    @endif

    @forelse($competitions as $comp)
    @php
        $response    = $comp->signupRequest?->responses->first();
        $isAttending = $response?->status === 'attending';
        $isDeclined  = $response?->status === 'not_attending';
        $isPending   = $response && $response->status === 'pending';
        $isFuture    = $comp->date->gte(today());
        $hasSignup   = $comp->signupRequest !== null;
        $hasEvents   = $comp->events->isNotEmpty();
        $hasEntries  = $comp->entries->isNotEmpty();
        $hasResults  = $comp->processedResults->isNotEmpty();
        $hasAnalysis = !empty($comp->analysis_text);
        $hasOrg      = $comp->organizer || $comp->ausrichter || !empty($comp->venue_details) || $comp->description;
        $hasAnnounce = $comp->announcement_pdf_path || !empty($comp->announcement_data['events']) || !empty($comp->announcement_data['deadlines']);

        $initialTab = match(true) {
            $isFuture && $hasSignup && ($isPending || !$response) => 'signup',
            $hasResults  => 'results',
            $hasSignup   => 'signup',
            $hasEvents   => 'schedule',
            $hasEntries  => 'entries',
            $hasOrg      => 'org',
            default      => 'signup',
        };
        $autoOpen = $isPending ? 'true' : 'false';
        $discLabels = ['F' => 'Freistil', 'B' => 'Brust', 'R' => 'Rücken', 'S' => 'Schmetterling', 'L' => 'Lagen'];
    @endphp

    <div x-data="{ open: {{ $autoOpen }}, tab: '{{ $initialTab }}' }"
         class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- ── Header ──────────────────────────────────────────────── --}}
        <div class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none" @click="open = !open">
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-gray-800 text-sm">{{ $comp->name }}</span>
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium shrink-0">{{ $comp->type_label }}</span>
                    @if($comp->course)
                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0">{{ $comp->course }}</span>
                    @endif
                </div>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $comp->date_range }}
                    @if($comp->location) · {{ $comp->location }} @endif
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if($response)
                    @if($isAttending)
                        <span class="text-xs bg-green-100 text-green-700 px-2.5 py-1 rounded-full font-semibold">Zugesagt</span>
                    @elseif($isDeclined)
                        <span class="text-xs bg-red-100 text-red-600 px-2.5 py-1 rounded-full font-semibold">Abgesagt</span>
                    @else
                        <span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full font-semibold">Ausstehend</span>
                    @endif
                @elseif($isFuture && $hasSignup)
                    <span class="text-xs bg-gray-100 text-gray-400 px-2.5 py-1 rounded-full">Kein Status</span>
                @endif
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        {{-- ── Expandable body ─────────────────────────────────────── --}}
        <div x-show="open" x-cloak class="border-t border-gray-100">

            {{-- Tab nav --}}
            <div class="flex overflow-x-auto border-b border-gray-100 bg-gray-50/50">
                @if($hasSignup)
                    <button @click.stop="tab = 'signup'"
                            :class="tab === 'signup' ? 'border-b-2 border-primary text-primary font-semibold bg-white' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition-colors">
                        Anmeldung
                        @if($isPending)<span class="ml-1 inline-block w-1.5 h-1.5 bg-amber-500 rounded-full align-middle"></span>@endif
                    </button>
                @endif
                @if($hasEvents)
                    <button @click.stop="tab = 'schedule'"
                            :class="tab === 'schedule' ? 'border-b-2 border-primary text-primary font-semibold bg-white' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition-colors">
                        Wettkampffolge
                    </button>
                @endif
                @if($hasEntries)
                    <button @click.stop="tab = 'entries'"
                            :class="tab === 'entries' ? 'border-b-2 border-primary text-primary font-semibold bg-white' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition-colors">
                        Meine Meldungen
                    </button>
                @endif
                @if($hasResults)
                    <button @click.stop="tab = 'results'"
                            :class="tab === 'results' ? 'border-b-2 border-primary text-primary font-semibold bg-white' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition-colors">
                        Ergebnisse
                    </button>
                @endif
                @if($hasOrg)
                    <button @click.stop="tab = 'org'"
                            :class="tab === 'org' ? 'border-b-2 border-primary text-primary font-semibold bg-white' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition-colors">
                        Organisation
                    </button>
                @endif
                @if($hasAnnounce)
                    <button @click.stop="tab = 'announce'"
                            :class="tab === 'announce' ? 'border-b-2 border-primary text-primary font-semibold bg-white' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition-colors">
                        Ausschreibung
                    </button>
                @endif
                @if($hasAnalysis)
                    <button @click.stop="tab = 'analysis'"
                            :class="tab === 'analysis' ? 'border-b-2 border-primary text-primary font-semibold bg-white' : 'text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2.5 text-xs whitespace-nowrap transition-colors">
                        Auswertung
                    </button>
                @endif
            </div>

            {{-- ── Anmeldung ───────────────────────────────────────── --}}
            @if($hasSignup)
            @php $signupRequest = $comp->signupRequest; @endphp
            <div x-show="tab === 'signup'" x-cloak class="p-5 space-y-3">

                @if($signupRequest->message)
                    <p class="text-sm text-gray-600 bg-blue-50/60 rounded-lg px-3 py-2 border border-blue-100">{{ $signupRequest->message }}</p>
                @endif

                <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                    @if($signupRequest->meeting_point)
                        <span><span class="font-medium text-gray-700">Treffpunkt:</span> {{ $signupRequest->meeting_point }}</span>
                    @endif
                    @if($signupRequest->meeting_time)
                        <span><span class="font-medium text-gray-700">Uhrzeit:</span> {{ substr($signupRequest->meeting_time, 0, 5) }} Uhr</span>
                    @endif
                </div>

                @if($signupRequest->bus_available)
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17h8M7 9h10M5 21V7a2 2 0 012-2h10a2 2 0 012 2v14"/>
                        </svg>
                        <span class="text-gray-600">Bus verfügbar ({{ $signupRequest->bus_seats ?? '–' }} Plätze)</span>
                        @if($response?->bus_booked)
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Busplatz gebucht</span>
                        @endif
                    </div>
                @endif

                @if($signupRequest->deadline)
                    <p class="text-xs font-medium {{ now()->gt($signupRequest->deadline) ? 'text-red-500' : 'text-amber-600' }}">
                        Anmeldefrist: {{ $signupRequest->deadline->format('d.m.Y') }}
                    </p>
                @endif

                @if($signupRequest->isActive() && $response)
                    <form method="POST" action="{{ route('swimmer.signup.respond', $signupRequest) }}"
                          class="space-y-4 pt-4 border-t border-gray-100">
                        @csrf
                        <div>
                            <p class="text-xs font-semibold text-gray-600 mb-2">Meine Teilnahme</p>
                            <div class="flex flex-wrap gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="attending"
                                           {{ $isAttending ? 'checked' : '' }} class="sr-only peer">
                                    <span class="inline-flex items-center px-4 py-2 rounded-lg border-2 text-sm font-medium transition-colors
                                        peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700
                                        border-gray-200 text-gray-600 hover:border-gray-300">Zusagen</span>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="not_attending"
                                           {{ $isDeclined ? 'checked' : '' }} class="sr-only peer">
                                    <span class="inline-flex items-center px-4 py-2 rounded-lg border-2 text-sm font-medium transition-colors
                                        peer-checked:border-red-400 peer-checked:bg-red-50 peer-checked:text-red-600
                                        border-gray-200 text-gray-600 hover:border-gray-300">Absagen</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Notiz (optional)</label>
                            <input type="text" name="note" value="{{ old('note', $response->note) }}"
                                   placeholder="z.B. Verspätung möglich"
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                        </div>
                        @if($signupRequest->offer_overnight)
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="wants_overnight" value="0">
                                <input type="checkbox" name="wants_overnight" value="1" id="overnight_{{ $comp->id }}"
                                       {{ ($response->wants_overnight ?? false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4">
                                <label for="overnight_{{ $comp->id }}" class="text-sm text-gray-700 cursor-pointer">Mit dem Team übernachten</label>
                            </div>
                        @endif
                        @if($signupRequest->offer_dinner)
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="wants_dinner" value="0">
                                <input type="checkbox" name="wants_dinner" value="1" id="dinner_{{ $comp->id }}"
                                       {{ ($response->wants_dinner ?? false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4">
                                <label for="dinner_{{ $comp->id }}" class="text-sm text-gray-700 cursor-pointer">Am gemeinsamen Abendessen teilnehmen</label>
                            </div>
                        @endif
                        <button type="submit"
                                class="bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
                            Speichern
                        </button>
                    </form>
                    @if($signupRequest->bus_available && $isAttending)
                        <form method="POST" action="{{ route('swimmer.signup.bus', $signupRequest) }}" class="mt-2">
                            @csrf
                            <button type="submit"
                                    class="text-xs px-4 py-2 rounded-lg border border-blue-200 text-blue-600 hover:bg-blue-50 transition-colors">
                                {{ $response->bus_booked ? 'Busplatz stornieren' : 'Busplatz buchen' }}
                            </button>
                        </form>
                    @endif
                @elseif($response)
                    <div class="pt-4 border-t border-gray-100">
                        <p class="text-sm text-gray-600">
                            Anmeldung {{ $signupRequest->isClosed() ? 'geschlossen' : 'nicht mehr aktiv' }} –
                            Status:
                            @if($isAttending)<span class="text-green-700 font-semibold">Zugesagt</span>
                            @elseif($isDeclined)<span class="text-red-600 font-semibold">Abgesagt</span>
                            @else<span class="text-amber-600 font-semibold">Ausstehend</span>@endif
                        </p>
                        @if($response->note)
                            <p class="text-xs text-gray-400 mt-1">Notiz: {{ $response->note }}</p>
                        @endif
                    </div>
                @else
                    <div class="pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-400">Keine Anmeldung für diesen Wettkampf hinterlegt.</p>
                    </div>
                @endif
            </div>
            @endif

            {{-- ── Wettkampffolge ──────────────────────────────────── --}}
            @if($hasEvents)
            <div x-show="tab === 'schedule'" x-cloak class="p-5">
                @php $sessionGroups = $comp->events->groupBy('session_number'); @endphp
                <div class="space-y-5">
                    @foreach($sessionGroups as $sessionNum => $events)
                        @php $first = $events->first(); @endphp
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-semibold text-gray-700 uppercase tracking-wide">
                                    Abschnitt {{ $sessionNum }}@if($first->session_name) – {{ $first->session_name }}@endif
                                </span>
                                @if($first->session_date)
                                    <span class="text-xs text-gray-400">{{ $first->session_date->format('d.m.Y') }}</span>
                                @endif
                            </div>
                            <div class="divide-y divide-gray-50">
                                @foreach($events as $event)
                                    <div class="flex items-center gap-3 py-1.5 text-sm">
                                        <span class="text-xs text-gray-400 w-7 shrink-0 font-mono">{{ $event->event_number }}</span>
                                        <span class="text-gray-700 flex-1">
                                            {{ $event->distance }} m {{ $event->discipline_label }}
                                            @if($event->gender !== 'X') · {{ $event->gender === 'M' ? 'Männlich' : 'Weiblich' }}@endif
                                            @if($event->age_group) · {{ $event->age_group }}@endif
                                        </span>
                                        @if($event->formatted_qualifying_time)
                                            <span class="text-xs font-mono text-gray-500 shrink-0">MZ {{ $event->formatted_qualifying_time }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── Meine Meldungen ─────────────────────────────────── --}}
            @if($hasEntries)
            <div x-show="tab === 'entries'" x-cloak class="p-5">
                <div class="divide-y divide-gray-50">
                    @foreach($comp->entries as $entry)
                        <div class="flex items-center justify-between py-2.5">
                            <span class="text-sm text-gray-700">
                                {{ $entry->distance }} m {{ $discLabels[$entry->discipline] ?? $entry->discipline }}
                                @if($entry->age_group) · {{ $entry->age_group }}@endif
                            </span>
                            <div class="flex items-center gap-2">
                                @if($entry->entry_time_ms)
                                    <span class="font-mono font-semibold text-primary text-sm">{{ $entry->entry_time_formatted }}</span>
                                @else
                                    <span class="text-xs text-gray-400">Ohne Meldezeit</span>
                                @endif
                                @if($entry->status === 'entered')
                                    <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium">Gemeldet</span>
                                @elseif($entry->status)
                                    <span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full">{{ ucfirst($entry->status) }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── Ergebnisse ───────────────────────────────────────── --}}
            @if($hasResults)
            <div x-show="tab === 'results'" x-cloak class="p-5">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-50">
                        @foreach($comp->processedResults as $swim)
                            <tr class="{{ $swim->is_dns ? 'opacity-60' : 'hover:bg-gray-50' }}">
                                <td class="py-2.5 pr-3 text-gray-700 w-1/3">
                                    {{ $swim->distance }} m {{ $swim->discipline_label }}
                                    @if($swim->is_final)
                                        <span class="ml-1 text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-medium">Finale</span>
                                    @endif
                                </td>
                                <td class="py-2.5">
                                    @if(!$swim->is_dns)
                                        <span class="font-mono font-semibold text-primary">{{ $swim->formatted_time }}</span>
                                        @if($swim->pb_badge)
                                            @php $pbColors = match($swim->pb_badge) { 'PB' => 'bg-green-100 text-green-700', 'JB' => 'bg-teal-100 text-teal-700', 'SB' => 'bg-cyan-100 text-cyan-700', default => 'bg-gray-100 text-gray-600' }; @endphp
                                            <span class="ml-1.5 text-xs {{ $pbColors }} px-1.5 py-0.5 rounded-full font-bold">{{ $swim->pb_badge }}</span>
                                        @endif
                                        @foreach($swim->beaten_records ?? [] as $rec)
                                            <span class="ml-1 text-xs {{ $rec === 'VR' ? 'bg-primary text-white' : 'bg-amber-500 text-white' }} px-1.5 py-0.5 rounded-full font-bold">{{ $rec }}</span>
                                        @endforeach
                                    @elseif($swim->notes)
                                        <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-semibold tracking-wide">{{ $swim->notes }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 text-gray-500 text-xs hidden sm:table-cell">
                                    @if(!empty($swim->placements) && !$swim->is_dns)
                                        @foreach($swim->placements as $p)
                                            <span class="{{ $p->placement <= 3 ? 'font-bold text-amber-600' : '' }}">
                                                @if($p->age_group)<span class="text-gray-400 font-normal">{{ $p->age_group }}: </span>@endif
                                                Platz {{ $p->placement }}
                                            </span>{{ !$loop->last ? ' · ' : '' }}
                                        @endforeach
                                    @else –
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- ── Organisation ─────────────────────────────────────── --}}
            @if($hasOrg)
            <div x-show="tab === 'org'" x-cloak class="p-5">
                <dl class="space-y-3 text-sm">
                    @if($comp->organizer)
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Veranstalter</dt>
                            <dd class="text-gray-700">{{ $comp->organizer }}</dd>
                        </div>
                    @endif
                    @if($comp->ausrichter)
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Ausrichter</dt>
                            <dd class="text-gray-700">{{ $comp->ausrichter }}</dd>
                        </div>
                    @endif
                    @if($comp->level)
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Ebene</dt>
                            <dd class="text-gray-700">{{ $comp->level_label }}</dd>
                        </div>
                    @endif
                    @if($comp->description)
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Beschreibung</dt>
                            <dd class="text-gray-700">{{ $comp->description }}</dd>
                        </div>
                    @endif
                    @if(!empty($comp->venue_details))
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Wettkampfstätte</dt>
                            <dd class="text-gray-700 space-y-0.5">
                                @foreach($comp->venue_details as $key => $val)
                                    @if($val && !is_array($val))
                                        <span class="block"><span class="text-gray-400 text-xs">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span> {{ $val }}</span>
                                    @endif
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if(!empty($comp->kampfgericht))
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Kampfgericht</dt>
                            <dd class="text-gray-700 space-y-0.5">
                                @foreach($comp->kampfgericht as $official)
                                    <span class="block text-xs">{{ is_array($official) ? implode(' – ', array_filter($official)) : $official }}</span>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if(!empty($comp->contact_info['entry']))
                        @php $entry = $comp->contact_info['entry']; @endphp
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Meldestelle</dt>
                            <dd class="text-gray-700 space-y-0.5">
                                @if(is_array($entry))
                                    @foreach($entry as $k => $v)
                                        @if($v)
                                            <span class="block text-xs"><span class="text-gray-400">{{ ucfirst($k) }}:</span> {{ is_array($v) ? implode(', ', $v) : $v }}</span>
                                        @endif
                                    @endforeach
                                @else
                                    <span class="text-xs">{{ $entry }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if(!empty($comp->contact_info['deadlines']))
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Fristen</dt>
                            <dd class="text-gray-700 space-y-0.5">
                                @foreach($comp->contact_info['deadlines'] as $dl)
                                    <span class="block text-xs">
                                        <span class="text-gray-400">{{ $dl['label'] ?? $dl['type'] ?? 'Frist' }}:</span> {{ $dl['date'] ?? '–' }}
                                    </span>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if(!empty($comp->organisation_notes))
                        <div class="flex gap-3">
                            <dt class="font-medium text-gray-600 w-32 shrink-0">Hinweise</dt>
                            <dd class="text-gray-700 text-sm">
                                @foreach((array) $comp->organisation_notes as $note)
                                    <p class="mb-1">{{ is_array($note) ? implode(' ', $note) : $note }}</p>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
            @endif

            {{-- ── Ausschreibung ────────────────────────────────────── --}}
            @if($hasAnnounce)
            <div x-show="tab === 'announce'" x-cloak class="p-5 space-y-4">
                @if($comp->announcement_pdf_path)
                    <a href="{{ asset('storage/' . $comp->announcement_pdf_path) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm text-primary hover:underline font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Ausschreibung als PDF herunterladen
                    </a>
                @endif

                @if(!empty($comp->announcement_data['events']))
                    <div>
                        <h4 class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Wettkampfdisziplinen</h4>
                        <div class="divide-y divide-gray-50">
                            @foreach($comp->announcement_data['events'] as $evt)
                                @php
                                    $strokes = array_map(fn($s) => $discLabels[$s] ?? $s, explode('|', $evt['stroke'] ?? ''));
                                    $genders = explode('|', $evt['gender'] ?? 'Mixed');
                                    $genderLabel = in_array('Mixed', $genders) ? '' : ' · ' . implode('/', array_map(fn($g) => $g === 'M' ? 'M' : 'W', $genders));
                                @endphp
                                <div class="flex items-center gap-3 py-1.5 text-sm">
                                    <span class="text-gray-700 flex-1">
                                        {{ implode(' / ', $strokes) }}{{ $genderLabel }}
                                        @if(!empty($evt['age_group'])) · {{ $evt['age_group'] }}@endif
                                    </span>
                                    @if(!empty($evt['min_time']))
                                        <span class="text-xs font-mono text-gray-500 shrink-0">MZ {{ $evt['min_time'] }}</span>
                                    @endif
                                    @if(!empty($evt['top_n']))
                                        <span class="text-xs text-gray-400 shrink-0">Top {{ $evt['top_n'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($comp->announcement_data['deadlines']))
                    <div>
                        <h4 class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Fristen</h4>
                        <div class="divide-y divide-gray-50">
                            @foreach($comp->announcement_data['deadlines'] as $dl)
                                <div class="flex items-center justify-between py-1.5 text-sm">
                                    <span class="text-gray-600">{{ $dl['label'] ?? $dl['type'] ?? 'Frist' }}</span>
                                    <span class="font-medium text-gray-700">{{ $dl['date'] ?? '–' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            @endif

            {{-- ── Auswertung ───────────────────────────────────────── --}}
            @if($hasAnalysis)
            <div x-show="tab === 'analysis'" x-cloak class="p-5">
                <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $comp->analysis_text }}</div>
            </div>
            @endif

        </div>
    </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-12 text-center text-gray-400">
            Noch keine Wettkämpfe zugewiesen.
        </div>
    @endforelse

    @if($competitions->hasPages())
        <div class="flex items-center justify-center gap-1 text-sm py-2">
            @if($competitions->onFirstPage())
                <span class="px-3 py-1.5 rounded-lg text-gray-300 border border-gray-100">‹</span>
            @else
                <a href="{{ $competitions->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">‹</a>
            @endif
            @foreach($competitions->getUrlRange(max(1, $competitions->currentPage() - 2), min($competitions->lastPage(), $competitions->currentPage() + 2)) as $page => $url)
                @if($page == $competitions->currentPage())
                    <span class="px-3 py-1.5 rounded-lg bg-primary text-white font-medium">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">{{ $page }}</a>
                @endif
            @endforeach
            @if($competitions->hasMorePages())
                <a href="{{ $competitions->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg text-gray-600 border border-gray-200 hover:bg-gray-50 transition-colors">›</a>
            @else
                <span class="px-3 py-1.5 rounded-lg text-gray-300 border border-gray-100">›</span>
            @endif
        </div>
    @endif
</div>
@endsection
