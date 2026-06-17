@extends('layouts.app')
@section('title', 'Wettkampf-Anmeldungen – ' . $child->name)
@section('page-title', 'Wettkampf-Anmeldungen – ' . $child->name)

@section('content')
<div class="mt-2 space-y-5">

    <div class="flex items-center gap-3">
        <a href="{{ route('parent.dashboard') }}" class="text-sm text-gray-500 hover:text-primary">← Übersicht</a>
        <span class="text-gray-300">|</span>
        <h1 class="text-sm font-semibold text-gray-700">Offene Wettkampf-Anmeldungen für {{ $child->name }}</h1>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl px-4 py-3">{{ session('error') }}</div>
    @endif

    @if($signupRequests->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <p class="text-sm text-gray-400">Keine offenen Wettkampf-Anmeldungen für {{ $child->name }}.</p>
        </div>
    @else
        @foreach($signupRequests as $signupRequest)
            @php
                $response    = $signupRequest->responses->first();
                $competition = $signupRequest->competition;
                $isAttending = $response?->status === 'attending';
                $isDeclined  = $response?->status === 'not_attending';
                $isPending   = !$response || $response->status === 'pending';
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                {{-- Header --}}
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-start gap-3 justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-800">{{ $competition->name }}</h2>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ $competition->date->format('d.m.Y') }}
                            @if($competition->date_end && !$competition->date->eq($competition->date_end))
                                – {{ $competition->date_end->format('d.m.Y') }}
                            @endif
                            @if($competition->location) · {{ $competition->location }} @endif
                        </p>
                        @if($signupRequest->deadline)
                            <p class="text-xs {{ now()->gt($signupRequest->deadline) ? 'text-red-500' : 'text-amber-600' }} mt-1 font-medium">
                                Frist: {{ $signupRequest->deadline->format('d.m.Y') }}
                            </p>
                        @endif
                    </div>
                    <div>
                        @if($isAttending)
                            <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                Zugesagt
                            </span>
                        @elseif($isDeclined)
                            <span class="inline-flex items-center gap-1.5 bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm font-semibold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Abgesagt
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-sm font-semibold">
                                Ausstehend
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Message --}}
                @if($signupRequest->message)
                    <div class="px-5 py-3 bg-blue-50/40 border-b border-gray-100">
                        <p class="text-sm text-gray-600">{{ $signupRequest->message }}</p>
                    </div>
                @endif

                {{-- Meeting info --}}
                @if($signupRequest->meeting_point || $signupRequest->meeting_time)
                    <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap gap-4 text-sm text-gray-600">
                        @if($signupRequest->meeting_point)
                            <span><span class="font-medium">Treffpunkt:</span> {{ $signupRequest->meeting_point }}</span>
                        @endif
                        @if($signupRequest->meeting_time)
                            <span><span class="font-medium">Uhrzeit:</span> {{ substr($signupRequest->meeting_time, 0, 5) }} Uhr</span>
                        @endif
                    </div>
                @endif

                {{-- Current response info --}}
                @if($response && $response->note)
                    <div class="px-5 py-2 bg-gray-50 border-b border-gray-100">
                        <p class="text-xs text-gray-500">Notiz: {{ $response->note }}</p>
                    </div>
                @endif

                {{-- Response form --}}
                <div class="px-5 py-4">
                    <form method="POST" action="{{ route('parent.child.signup.respond', [$child->id, $signupRequest]) }}"
                          class="space-y-4">
                        @csrf

                        {{-- Status --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-600 mb-2">Teilnahme für {{ $child->firstname }}</p>
                            <div class="flex flex-wrap gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="attending"
                                           {{ $isAttending ? 'checked' : '' }} class="sr-only peer">
                                    <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border-2 text-sm font-medium transition-colors
                                        peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700
                                        border-gray-200 text-gray-600 hover:border-gray-300">
                                        Zusagen
                                    </span>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="status" value="not_attending"
                                           {{ $isDeclined ? 'checked' : '' }} class="sr-only peer">
                                    <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border-2 text-sm font-medium transition-colors
                                        peer-checked:border-red-400 peer-checked:bg-red-50 peer-checked:text-red-600
                                        border-gray-200 text-gray-600 hover:border-gray-300">
                                        Absagen
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- Note --}}
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Notiz (optional)</label>
                            <input type="text" name="note" value="{{ old('note', $response?->note) }}"
                                   placeholder="z.B. Verspätung möglich"
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                        </div>

                        {{-- Carpool offer --}}
                        <div class="border-t border-gray-100 pt-3">
                            <p class="text-xs font-semibold text-gray-600 mb-2">Fahrgemeinschaft</p>
                            <div class="flex items-center gap-3">
                                <label class="text-sm text-gray-700 whitespace-nowrap">Freie Plätze (außer Fahrer):</label>
                                <input type="number" name="carpool_seats"
                                       value="{{ old('carpool_seats', $response?->carpool_seats) }}"
                                       min="0" max="20" placeholder="0"
                                       class="w-20 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary outline-none">
                                <span class="text-xs text-gray-400">0 = keine Mitfahrmöglichkeit</span>
                            </div>
                        </div>

                        {{-- Overnight --}}
                        @if($signupRequest->offer_overnight)
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="wants_overnight" value="0">
                                <input type="checkbox" name="wants_overnight" value="1" id="overnight_{{ $signupRequest->id }}"
                                       {{ ($response?->wants_overnight ?? false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4">
                                <label for="overnight_{{ $signupRequest->id }}" class="text-sm text-gray-700 cursor-pointer">
                                    Mit dem Team übernachten
                                </label>
                            </div>
                        @endif

                        {{-- Dinner --}}
                        @if($signupRequest->offer_dinner)
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="wants_dinner" value="0">
                                <input type="checkbox" name="wants_dinner" value="1" id="dinner_{{ $signupRequest->id }}"
                                       {{ ($response?->wants_dinner ?? false) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-primary focus:ring-primary w-4 h-4">
                                <label for="dinner_{{ $signupRequest->id }}" class="text-sm text-gray-700 cursor-pointer">
                                    Am gemeinsamen Abendessen teilnehmen
                                </label>
                            </div>
                        @endif

                        <button type="submit"
                                class="bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
                            Speichern
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif

</div>
@endsection
