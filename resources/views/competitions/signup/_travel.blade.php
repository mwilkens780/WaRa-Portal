{{--
    Anreise zu einem Wettkampf: Vereinsbus und Fahrgemeinschaften buchen.
    Fuer Schwimmer selbst und fuer Eltern (minderjaehriger Kinder) - eine Fassung
    fuer "Meine Wettkaempfe", die Elternsicht und "Anmeldungen".

    Erwartet: $signupRequest, $response (des Schwimmers), $subject (Schwimmer), $asParent (bool)
    Nur einbinden, wenn zugesagt, Abfrage aktiv und Wettkampf noch nicht vorbei.
--}}
@php
    $routes = $asParent
        ? ['bus' => route('parent.child.signup.bus', [$subject->id, $signupRequest]),
           'carpool' => route('parent.child.signup.carpool', [$subject->id, $signupRequest]),
           'cancel' => route('parent.child.signup.carpool.cancel', [$subject->id, $signupRequest])]
        : ['bus' => route('swimmer.signup.bus', $signupRequest),
           'carpool' => route('swimmer.signup.carpool', $signupRequest),
           'cancel' => route('swimmer.signup.carpool.cancel', $signupRequest)];
    $wer    = $asParent ? $subject->firstname : 'Du';
    $offers = $signupRequest->carpoolOffers()->reject(fn($o) => $o->id === $response->id);
    $busFree = $signupRequest->bus_available ? $signupRequest->busSeatsRemaining() : 0;
@endphp

<section class="pt-4 border-t border-gray-100 space-y-3" aria-label="Anreise">
    <h3 class="text-sm font-semibold text-gray-800">Anreise{{ $asParent ? ' von ' . $subject->firstname : '' }}</h3>

    {{-- Vereinsbus --}}
    @if($signupRequest->bus_available)
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2">
            <div class="text-sm">
                <span class="font-medium text-gray-800">Vereinsbus</span>
                @if($response->bus_booked)
                    <x-ui.badge tone="info" class="ml-1">gebucht</x-ui.badge>
                @endif
                <span class="block text-xs text-gray-600">{{ $busFree }} von {{ $signupRequest->bus_seats }} Plätzen frei</span>
            </div>
            @if($response->bus_booked || $busFree > 0)
                <form method="POST" action="{{ $routes['bus'] }}">
                    @csrf
                    <x-ui.button type="submit" size="sm" variant="secondary">{{ $response->bus_booked ? 'Busplatz stornieren' : 'Busplatz buchen' }}</x-ui.button>
                </form>
            @else
                <span class="text-xs text-gray-600">ausgebucht</span>
            @endif
        </div>
    @endif

    {{-- Fahrgemeinschaften (bieten Eltern an) --}}
    <div>
        <p class="text-xs font-semibold text-gray-600 mb-1.5">Fahrgemeinschaften</p>
        @forelse($offers as $offer)
            @php $free = $offer->carpoolSeatsRemaining(); $mine = $response->carpool_ride_id === $offer->id; @endphp
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 mb-2 {{ $mine ? 'border-blue-300 bg-blue-50' : 'border-gray-200' }}">
                <div class="text-sm min-w-0">
                    <span class="font-medium text-gray-800">{{ $offer->carpoolDriverLabel() }}</span>
                    @if($mine)<x-ui.badge tone="info" class="ml-1">{{ $wer }} {{ $asParent ? 'fährt' : 'fährst' }} mit</x-ui.badge>@endif
                    <span class="block text-xs text-gray-600">{{ $free }} von {{ $offer->carpool_seats }} Plätzen frei</span>
                    @if($offer->carpool_note)<span class="block text-xs text-gray-700 italic break-words">{{ $offer->carpool_note }}</span>@endif
                    {{-- Nur, wenn der Anbieter die Nummer fuer dieses Angebot freigegeben hat --}}
                    @if($tel = $offer->carpoolPhone())
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $tel) }}" class="block text-xs text-primary underline underline-offset-2">{{ $tel }}</a>
                    @endif
                </div>
                @if($mine)
                    <form method="POST" action="{{ $routes['cancel'] }}">
                        @csrf @method('DELETE')
                        <x-ui.button type="submit" size="sm" variant="secondary">Mitfahrt stornieren</x-ui.button>
                    </form>
                @elseif($free > 0)
                    <form method="POST" action="{{ $routes['carpool'] }}"
                          @if($response->bus_booked) data-confirm="Mitfahren statt Bus?" data-confirm-text="Der Busplatz wird dafür freigegeben." data-confirm-label="Mitfahren" @endif>
                        @csrf
                        <input type="hidden" name="offer_id" value="{{ $offer->id }}">
                        <x-ui.button type="submit" size="sm" variant="secondary">Mitfahren</x-ui.button>
                    </form>
                @else
                    <span class="text-xs text-gray-600">voll</span>
                @endif
            </div>
        @empty
            <p class="text-xs text-gray-600">Noch keine Fahrgemeinschaft angeboten.{{ $asParent ? ' Eigenes Angebot oben im Formular eintragen.' : '' }}</p>
        @endforelse
    </div>
</section>
