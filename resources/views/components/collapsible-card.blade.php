{{--
    Aufklappbare Karte mit Kopfzeile.

    Der Zustand wird je Karte im Browser gemerkt, damit man nach jedem
    Seitenwechsel nicht wieder alles zuklappen muss. Der Zugriff auf den
    Speicher ist abgesichert: In privaten Fenstern oder bei blockierten
    Website-Daten wirft er, und dann soll die Karte trotzdem funktionieren -
    sie startet dann eben im Standardzustand.

    Verwendung:
        <x-collapsible-card title="Bestzeiten" meta="12 Strecken" storage-key="dash-bests">
            … Inhalt …
        </x-collapsible-card>
--}}
@props([
    'title',
    'meta'       => null,
    'storageKey' => null,
    'open'       => true,
    'id'         => null,
])

<div @if($id) id="{{ $id }}" @endif
     class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
     x-data="{
        open: {{ $open ? 'true' : 'false' }},
        key: @js($storageKey),
        init() {
            if (!this.key) return;
            try {
                const saved = localStorage.getItem('card:' + this.key);
                if (saved !== null) this.open = saved === '1';
            } catch (e) {}
        },
        toggle() {
            this.open = !this.open;
            if (!this.key) return;
            try { localStorage.setItem('card:' + this.key, this.open ? '1' : '0'); } catch (e) {}
        },
     }">

    <button type="button" @click="toggle()"
            class="w-full flex items-center justify-between gap-3 p-5 text-left hover:bg-gray-50 transition-colors"
            :class="open ? 'border-b border-gray-100' : ''"
            :aria-expanded="open ? 'true' : 'false'">
        <h2 class="font-semibold text-gray-800">{{ $title }}</h2>
        <span class="flex items-center gap-3 flex-shrink-0">
            @if($meta)
                <span class="text-xs text-gray-400">{{ $meta }}</span>
            @endif
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </button>

    <div x-show="open" x-cloak>
        {{ $slot }}
    </div>
</div>
