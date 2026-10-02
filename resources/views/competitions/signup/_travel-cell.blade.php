{{-- Anreise eines Schwimmers fuer die Trainer-Tabelle. Erwartet: $response --}}
@if($response->bus_booked)
    <x-ui.badge tone="info">Bus</x-ui.badge>
@elseif($response->carpoolRide)
    <span class="text-xs text-gray-700">Mitfahrt bei {{ $response->carpoolRide->carpoolDriverLabel() }}</span>
@endif
@if($response->carpool_seats && $response->isAttending())
    <span class="block text-xs text-gray-700 {{ $response->bus_booked || $response->carpoolRide ? 'mt-1' : '' }}">
        <x-ui.badge tone="success">bietet {{ $response->carpool_seats }} Plätze</x-ui.badge>
        {{ $response->carpoolPassengers->count() }} belegt{{ $response->carpoolPassengers->isNotEmpty() ? ': ' . $response->carpoolPassengers->map(fn($p) => $p->user?->firstname)->filter()->implode(', ') : '' }}
        @if($response->carpool_note)<span class="block italic text-gray-600">{{ $response->carpool_note }}</span>@endif
    </span>
@endif
@if(!$response->bus_booked && !$response->carpoolRide && !($response->carpool_seats && $response->isAttending()))
    <span class="text-xs text-gray-600">–</span>
@endif
