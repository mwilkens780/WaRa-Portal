{{--
    Aktionsleiste fuer Schritt 2 (Vorschau) eines Imports - steht innerhalb
    des Formulars und klebt am unteren Rand, damit "Übernehmen" auch bei
    langen Listen ohne Scrollen erreichbar ist.

    <x-ui.import-bar :cancel="route('admin.records.index')" count="input[name$='[include]']:checked"
                     singular="Rekord" plural="Rekorde" />
    <x-ui.import-bar :cancel="..." :total="12" plural="Belegungen" />

    count:  CSS-Selektor der ausgewaehlten Zeilen im Formular - die Zahl
            folgt der Auswahl live ("12 Rekorde übernehmen")
    total:  feste Zahl, wenn es nichts auszuwaehlen gibt
    Ohne beides: nur "Übernehmen". Bei 0 ist der Knopf gesperrt.
    Slot: zusaetzliche Hinweise links (z. B. "3 Zeilen mit Fehlern werden übersprungen")
--}}
@props([
    'cancel',
    'count'    => null,
    'total'    => null,
    'singular' => null,
    'plural'   => null,
    'verb'     => 'übernehmen',
])

<div x-data="{
        n: {{ $total !== null ? (int) $total : 'null' }},
        busy: false,
        sel: @js($count),
        label() {
            if (this.n === null) return @js(ucfirst($verb));
            const noun = this.n === 1 ? @js($singular ?? $plural ?? '') : @js($plural ?? '');
            return (this.n + ' ' + noun + ' ' + @js($verb)).replace(/\s+/g, ' ');
        },
        init() {
            const form = this.$el.closest('form');
            if (!form) return;
            if (this.sel) {
                // Auch Alle-auswaehlen per Skript loest kein change aus - daher zusaetzlich nach Klicks zaehlen
                const upd = () => { this.n = form.querySelectorAll(this.sel).length; };
                form.addEventListener('change', upd);
                form.addEventListener('click', () => setTimeout(upd));
                upd();
            }
            form.addEventListener('submit', (e) => { if (!e.defaultPrevented) this.busy = true; });
            window.addEventListener('pageshow', () => { this.busy = false; });
        },
     }"
     {{ $attributes->merge(['class' => 'sticky bottom-0 z-20 -mx-4 sm:mx-0 mt-4 border-t border-gray-200 bg-white/95 backdrop-blur px-4 py-3 sm:rounded-b-xl']) }}
     style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
    <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-0 flex-1 text-sm text-gray-700">{{ $slot }}</div>
        <a href="{{ $cancel }}"
           class="inline-flex items-center justify-center min-h-[44px] sm:min-h-[40px] px-4 rounded-lg text-sm font-semibold border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">
            Abbrechen
        </a>
        <button type="submit" :disabled="busy || n === 0" :aria-busy="busy ? 'true' : 'false'"
                class="inline-flex items-center justify-center gap-2 min-h-[44px] sm:min-h-[40px] px-4 rounded-lg text-sm font-semibold
                       bg-primary text-white hover:bg-primary-dark transition-colors
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2
                       disabled:opacity-60 disabled:cursor-not-allowed">
            <svg x-show="busy" x-cloak class="w-4 h-4 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                <path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span x-text="busy ? 'Wird übernommen …' : label()">{{ ucfirst($verb) }}</span>
        </button>
    </div>
</div>
