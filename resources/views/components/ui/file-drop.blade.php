{{--
    Datei-Auswahl mit Ablegen-Flaeche (Import-Assistent, docs/frontend-audit.md 5.2).

    <x-ui.file-drop name="csv_file" label="WebClub-Export" accept=".csv,.txt" :max-mb="10"
                    hint="Export „Mitglieder“ aus WebClub, Trennzeichen Semikolon" />

    - Klick, Tastatur (Enter/Leertaste auf dem Feld) oder Ziehen und Ablegen
    - Dateityp und Groesse werden vorab geprueft (gleiche Grenzen wie der
      Server angeben!), eine ungueltige Datei sperrt das Absenden
    - Fehler des Servers (@error) stehen am Feld
--}}
@props([
    'name',
    'label',
    'accept'   => '',
    'maxMb'    => 0,
    'hint'     => null,
    'required' => true,
    'id'       => null,
])

@php
    $id       = $id ?? 'file-' . \Illuminate\Support\Str::slug($name);
    $error    = $errors->first($name);
    $formats  = collect(explode(',', $accept))->map(fn($e) => trim($e))->filter()->implode(', ');
    $describe = "{$id}-rules" . ($hint ? " {$id}-hint" : '') . " {$id}-error";
@endphp

<div x-data="uiFileDrop({ accept: @js($accept), maxMb: {{ (int) $maxMb }} })"
     {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-800">
        {{ $label }}@if($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif
    </label>

    <div @dragover.prevent="over = true" @dragenter.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="onDrop($event)"
         :class="over ? 'border-primary bg-primary/5' : (error ? 'border-red-400 bg-red-50/40' : (file ? 'border-green-400 bg-green-50/40' : 'border-gray-300 bg-gray-50/60'))"
         class="relative rounded-xl border-2 border-dashed transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary has-[:focus-visible]:ring-offset-2 @if($error) !border-red-400 @endif">

        {{-- Echtes Feld: optisch versteckt, fuer Tastatur und Screenreader da --}}
        <input x-ref="input" type="file" id="{{ $id }}" name="{{ $name }}" accept="{{ $accept }}"
               @if($required) required @endif
               aria-describedby="{{ $describe }}" @if($error) aria-invalid="true" @endif
               @change="onChange()"
               class="sr-only"
               {{ $attributes->except('class') }}>

        {{-- Noch keine Datei --}}
        {{-- Ganze Flaeche klickbar; per Tastatur ist das Feld selbst das Ziel --}}
        <div x-show="!file" @click="choose()" class="flex flex-col items-center gap-2 px-4 py-6 text-center cursor-pointer">
            <x-ui.icon name="upload" class="w-8 h-8 text-gray-500" />
            <p class="text-sm text-gray-700">
                <span class="hidden sm:inline">Datei hierher ziehen oder</span>
                <span class="font-semibold text-primary underline-offset-2 hover:underline">Datei auswählen</span>
            </p>
        </div>

        {{-- Gewaehlte Datei --}}
        <div x-show="file" x-cloak class="flex items-center gap-3 px-4 py-3">
            <x-ui.icon name="document" class="w-6 h-6 flex-shrink-0" x-bind:class="error ? 'text-red-600' : 'text-green-700'" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 truncate" x-text="file?.name"></p>
                <p class="text-xs text-gray-600" x-text="sizeLabel"></p>
            </div>
            <button type="button" @click="clear()"
                    class="flex-shrink-0 min-h-[44px] sm:min-h-[36px] px-3 rounded-lg text-sm font-medium text-gray-700 hover:bg-white border border-gray-300">
                Andere Datei
            </button>
        </div>
    </div>

    <p id="{{ $id }}-rules" class="text-xs text-gray-600">
        @if($formats)Formate: {{ $formats }}@endif
        @if($maxMb) · höchstens {{ $maxMb }} MB @endif
    </p>
    @if($hint)
        <p id="{{ $id }}-hint" class="text-xs text-gray-600">{{ $hint }}</p>
    @endif
    {{-- Browser-Pruefung, sonst Fehler vom Server --}}
    <p id="{{ $id }}-error" role="alert" class="text-xs font-medium text-red-700"
       x-text="error || @js($error ?: '')" x-show="error || @js((bool) $error)">{{ $error }}</p>
</div>
