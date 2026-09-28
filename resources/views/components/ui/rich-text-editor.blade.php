{{--
    Editor fuer formatierten Text (Tiptap), z. B. Wettkampf-Auswertung.

    Nur fuer Texte, die gedruckt, als PDF oder per Mail verschickt werden.
    Notizen und Kommentare sind einfache Textfelder (docs/frontend-audit.md, 8.2).

    Verwendung im Formular:
        <x-ui.rich-text-editor name="analysis_text" :value="$competition->analysis_text" label="Auswertung" />

    Ohne Formular (per fetch speichern): x-ref setzen und
    Alpine.$data($refs.editor).getHTML() lesen.

    Der Server muss den Text immer mit App\Support\RichText bereinigen.
--}}
@props([
    'name'        => null,
    'value'       => null,
    'label'       => 'Text',
    'placeholder' => '',
])

@php
    $tools = [
        ['bold',        'Fett',                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 5h6a3.5 3.5 0 010 7H7zm0 7h7a3.5 3.5 0 010 7H7z"/>'],
        ['italic',      'Kursiv',              '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 5h8M6 19h8M14 5l-4 14"/>'],
        ['underline',   'Unterstrichen',       '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v7a5 5 0 0010 0V4M5 20h14"/>'],
        ['h2',          'Überschrift',         '<text x="3" y="17" font-size="13" font-weight="700" fill="currentColor" stroke="none">H2</text>'],
        ['h3',          'Zwischenüberschrift', '<text x="3" y="17" font-size="13" font-weight="700" fill="currentColor" stroke="none">H3</text>'],
        ['bulletList',  'Aufzählung',          '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>'],
        ['orderedList', 'Nummerierung',        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6h10M10 12h10M10 18h10M4 5h1v4M4 9h2M4 15h2l-2 3h2"/>'],
        ['blockquote',  'Zitat',               '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7v10M9 8h10M9 12h10M9 16h6"/>'],
    ];
@endphp

<div x-data="richTextEditor({ value: @js(\App\Support\RichText::sanitize($value)), placeholder: @js($placeholder) })"
     data-label="{{ $label }}"
     {{ $attributes->merge(['class' => 'rounded-lg border border-gray-300 bg-white focus-within:ring-2 focus-within:ring-primary/30 focus-within:border-primary']) }}>

    {{-- Werkzeugleiste --}}
    <div role="toolbar" aria-label="Formatierung: {{ $label }}"
         class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 rounded-t-lg px-1.5 py-1">
        @foreach($tools as [$cmd, $title, $icon])
            <button type="button" @click="run('{{ $cmd }}')" :disabled="!ready"
                    :aria-pressed="active.{{ $cmd }} ? 'true' : 'false'"
                    :class="active.{{ $cmd }} ? 'bg-white text-primary shadow-sm' : 'text-gray-600 hover:bg-white/70'"
                    class="inline-flex items-center justify-center w-11 h-11 sm:w-9 sm:h-9 rounded-md disabled:opacity-40"
                    title="{{ $title }}" aria-label="{{ $title }}">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">{!! $icon !!}</svg>
            </button>
            @if(in_array($cmd, ['underline', 'h3', 'blockquote']))
                <span class="w-px h-5 bg-gray-200 mx-1" aria-hidden="true"></span>
            @endif
        @endforeach
        <button type="button" @click="toggleLink()" :disabled="!ready"
                :aria-pressed="active.link ? 'true' : 'false'"
                :class="active.link ? 'bg-white text-primary shadow-sm' : 'text-gray-600 hover:bg-white/70'"
                class="inline-flex items-center justify-center w-11 h-11 sm:w-9 sm:h-9 rounded-md disabled:opacity-40"
                title="Link" aria-label="Link">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        </button>
        <button type="button" @click="run('clear')" :disabled="!ready"
                class="inline-flex items-center justify-center w-11 h-11 sm:w-9 sm:h-9 rounded-md text-gray-600 hover:bg-white/70 disabled:opacity-40"
                title="Formatierung entfernen" aria-label="Formatierung entfernen">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6h12M12 6l-3 13M4 20L20 4"/></svg>
        </button>
    </div>

    {{-- Eingabe (Tiptap haengt sich hier ein) --}}
    <div x-ref="editor"></div>
    <p x-show="!ready" class="px-4 py-3 text-sm text-gray-400">Editor wird geladen…</p>

    @if($name)
        <input type="hidden" name="{{ $name }}" :value="html">
    @endif
</div>
