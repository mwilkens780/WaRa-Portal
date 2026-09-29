{{--
    Aufklappmenue ("Weitere Aktionen", Saisonwahl ...).

    <x-ui.menu label="Weitere Aktionen" icon="dots">
        <x-ui.menu-item href="{{ route('...') }}">Exportieren</x-ui.menu-item>
        <x-ui.menu-item tone="danger" @click="...">Löschen</x-ui.menu-item>
    </x-ui.menu>

    Mit Button-Text statt Symbol: <x-ui.menu label="Saison 2026/27" :text="true">
    Tastatur: Pfeile hoch/runter, Escape schliesst und gibt den Fokus zurueck.
--}}
@props(['label', 'icon' => 'dots', 'text' => false, 'align' => 'right'])

<div x-data="{ open: false }" class="relative inline-block" {{ $attributes->only('class') }}
     @keydown.escape.stop="if (open) { open = false; $refs.trigger.focus() }"
     @click.outside="open = false">
    <button type="button" x-ref="trigger" @click="open = !open; if (open) $nextTick(() => $focus.within($refs.panel).first())"
            aria-haspopup="menu" :aria-expanded="open ? 'true' : 'false'"
            @if(!$text) aria-label="{{ $label }}" title="{{ $label }}" @endif
            class="{{ $text
                ? 'inline-flex items-center gap-1.5 min-h-[36px] px-3 rounded-full border border-blue-100 bg-blue-50 text-primary text-xs font-semibold hover:bg-blue-100'
                : 'inline-flex items-center justify-center w-11 h-11 sm:w-9 sm:h-9 rounded-lg text-gray-600 hover:bg-gray-100' }}
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
        @if($text)
            {{ $label }}<x-ui.icon name="chevron-down" class="w-3.5 h-3.5 opacity-70" />
        @else
            <x-ui.icon :name="$icon" class="w-5 h-5" />
        @endif
    </button>
    <div x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.100ms role="menu" aria-label="{{ $label }}"
         @keydown.arrow-down.prevent="$focus.within($refs.panel).wrap().next()" @keydown.arrow-up.prevent="$focus.within($refs.panel).wrap().previous()"
         @click="open = false"
         class="absolute {{ $align === 'left' ? 'left-0' : 'right-0' }} z-50 mt-1 min-w-[12rem] rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
        {{ $slot }}
    </div>
</div>
