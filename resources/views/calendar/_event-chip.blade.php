{{--
    Termin im Kalender als Knopf: oeffnet das Detail-Sheet (calendar/_event-sheet).

    Vorher Links bzw. Kaesten mit Tooltip und einem Bearbeiten-Stift, der nur
    bei Maus-Hover erschien - auf Touch-Geraeten und per Tastatur kam man an
    Details und Bearbeiten nicht heran.

    Erwartet: $evt (Termin aus dem Controller), $day (Carbon), $variant ('week'|'month'|'agenda'),
              $colorMap, $isTrainer
--}}
@php
    $chip    = $colorMap[$evt['color']] ?? $colorMap['gray'];
    $payload = \App\Support\CalendarEventPayload::make($evt, $day, $isTrainer);
    $classes = [
        'week'   => 'block w-full text-left text-xs rounded px-1.5 py-1',
        'month'  => 'flex w-full items-center gap-1 text-left text-xs leading-tight px-1.5 py-1 rounded',
        'agenda' => 'flex w-full items-start gap-3 text-left text-sm rounded-lg px-3 py-2.5 min-h-[44px]',
    ][$variant];
@endphp
<button type="button"
        x-show="categories['{{ $evt['color'] }}'] !== false"
        @click="showEvents(@js($payload['date']), [@js($payload)])"
        aria-label="{{ $payload['aria'] }}"
        class="{{ $classes }} {{ $variant === 'agenda' ? 'bg-white border border-gray-100 hover:bg-gray-50' : $chip['chip'] . ' hover:brightness-95' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
    @if($variant === 'week')
        @if($evt['time'])<span class="block font-bold text-xs opacity-80">{{ $evt['time'] }}</span>@endif
        <span class="block font-medium leading-snug truncate">{{ $evt['title'] }}</span>
        @if($evt['sub'])<span class="block opacity-80 truncate leading-tight text-xs">{{ $evt['sub'] }}</span>@endif
    @elseif($variant === 'month')
        @if($evt['time'])<span class="font-semibold flex-shrink-0">{{ $evt['time'] }}</span>@endif
        <span class="truncate">{{ $evt['title'] }}</span>
    @else
        <span class="mt-1.5 w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $chip['dot'] }}" aria-hidden="true"></span>
        <span class="min-w-0 flex-1">
            <span class="block font-medium text-gray-900">{{ $evt['title'] }}</span>
            @if($evt['time'] || $evt['sub'])
                <span class="block text-xs text-gray-600">{{ collect([$evt['time'], $evt['sub']])->filter()->implode(' · ') }}</span>
            @endif
        </span>
    @endif
</button>
