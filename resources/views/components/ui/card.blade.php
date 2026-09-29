{{--
    Karte mit optionalem Kopf, auf Wunsch aufklappbar (Zustand je Browser gemerkt).

    <x-ui.card title="Bestzeiten" meta="12 Strecken">…</x-ui.card>
    <x-ui.card title="Legende" collapsible storage-key="hall-legend" :open="false">…</x-ui.card>
    <x-ui.card :padded="false">…Tabelle ohne Innenabstand…</x-ui.card>

    Slot "actions" erscheint rechts im Kopf (nicht bei collapsible).
--}}
@props([
    'title'       => null,
    'meta'        => null,
    'collapsible' => false,
    'storageKey'  => null,
    'open'        => true,
    'padded'      => true,
])

@php $bodyPad = $padded ? 'p-5' : ''; @endphp

@if($collapsible)
    <section {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden']) }}
             x-data="{
                open: {{ $open ? 'true' : 'false' }},
                key: @js($storageKey),
                init() { if (!this.key) return; try { const s = localStorage.getItem('card:' + this.key); if (s !== null) this.open = s === '1'; } catch (e) {} },
                toggle() { this.open = !this.open; if (!this.key) return; try { localStorage.setItem('card:' + this.key, this.open ? '1' : '0'); } catch (e) {} },
             }">
        <h2>
            <button type="button" @click="toggle()" :aria-expanded="open ? 'true' : 'false'"
                    class="w-full flex items-center justify-between gap-3 px-5 py-4 text-left hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                    :class="open ? 'border-b border-gray-100' : ''">
                <span class="font-semibold text-gray-900">{{ $title }}</span>
                <span class="flex items-center gap-3 flex-shrink-0">
                    @if($meta)<span class="text-xs text-gray-600">{{ $meta }}</span>@endif
                    <x-ui.icon name="chevron-down" class="w-4 h-4 text-gray-500 transition-transform" ::class="open ? 'rotate-180' : ''" />
                </span>
            </button>
        </h2>
        <div x-show="open" x-cloak class="{{ $bodyPad }}">{{ $slot }}</div>
    </section>
@else
    <section {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden']) }}>
        @if($title || isset($actions))
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    @if($title)<h2 class="font-semibold text-gray-900">{{ $title }}</h2>@endif
                    @if($meta)<p class="text-xs text-gray-600 mt-0.5">{{ $meta }}</p>@endif
                </div>
                @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
            </div>
        @endif
        <div class="{{ $bodyPad }}">{{ $slot }}</div>
    </section>
@endif
