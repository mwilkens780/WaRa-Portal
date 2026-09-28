{{--
    Formatierten Text aus dem Editor anzeigen.

    Der Inhalt wird hier immer bereinigt (App\Support\RichText) - nie selbst
    mit {!! !!} ausgeben. Typografie fuer Absaetze, Listen, Ueberschriften und
    Links kommt von hier, damit Auswertungen ueberall gleich aussehen.

    Verwendung:
        <x-ui.rich-text :html="$competition->analysis_text" />
--}}
@props(['html' => null])

<div {{ $attributes->merge(['class' =>
    'text-sm text-gray-700 leading-relaxed break-words
     [&_p]:mb-2 [&_p:last-child]:mb-0
     [&_h1]:text-lg [&_h1]:font-bold [&_h1]:mt-3 [&_h1]:mb-2
     [&_h2]:text-base [&_h2]:font-semibold [&_h2]:mt-3 [&_h2]:mb-1.5
     [&_h3]:font-semibold [&_h3]:mt-2 [&_h3]:mb-1
     [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-2
     [&_blockquote]:border-l-4 [&_blockquote]:border-gray-200 [&_blockquote]:pl-3 [&_blockquote]:text-gray-600
     [&_a]:text-primary [&_a]:underline'
]) }}>{!! \App\Support\RichText::sanitize($html) !!}</div>
