{{--
    Formular fuer Schritt 1 eines Imports: Datei(en) + weitere Felder im Slot,
    ein einheitlicher Absende-Knopf mit Ladezustand.

    <x-ui.upload-form :action="route('admin.webclub-import.upload')" submit="Einlesen und prüfen">
        <x-ui.file-drop name="csv_file" label="WebClub-Export" accept=".csv,.txt" :max-mb="10" />
    </x-ui.upload-form>

    - Der Knopf ist gesperrt, bis eine gueltige Datei gewaehlt ist
    - Nach dem Absenden: "Wird eingelesen …", kein Doppelklick-Upload
    - Zurueck-Taste des Browsers (bfcache) gibt den Knopf wieder frei
    - data-confirm="…" am Formular: erst Rueckfrage, Ladezustand erst danach
      (der Rueckfrage-Handler bricht das erste Absenden in der Capture-Phase ab)
    layout: stack (Standard) | inline (Feld und Knopf nebeneinander, fuer Karten)
--}}
@props([
    'action',
    'submit' => 'Einlesen und prüfen',
    'layout' => 'stack',
    'method' => 'POST',
])

<form method="{{ $method }}" action="{{ $action }}" enctype="multipart/form-data"
      x-data="{ ready: false, busy: false }"
      @file-change="ready = $event.detail.valid"
      @submit="if (!$event.defaultPrevented) busy = true"
      @pageshow.window="busy = false"
      {{ $attributes->merge(['class' => $layout === 'inline' ? 'flex flex-col sm:flex-row sm:items-start gap-3' : 'space-y-4']) }}>
    @csrf

    <div class="{{ $layout === 'inline' ? 'flex-1 min-w-0 space-y-4' : 'space-y-4' }}">
        {{ $slot }}
    </div>

    <div class="{{ $layout === 'inline' ? 'sm:pt-7' : '' }}">
        <button type="submit" :disabled="!ready || busy" :aria-busy="busy ? 'true' : 'false'"
                class="inline-flex w-full sm:w-auto items-center justify-center gap-2 min-h-[44px] sm:min-h-[40px] px-4 rounded-lg text-sm font-semibold
                       bg-primary text-white hover:bg-primary-dark transition-colors
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2
                       disabled:opacity-60 disabled:cursor-not-allowed">
            <svg x-show="busy" x-cloak class="w-4 h-4 motion-safe:animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                <path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span x-text="busy ? 'Wird eingelesen …' : @js($submit)">{{ $submit }}</span>
        </button>
    </div>
</form>
