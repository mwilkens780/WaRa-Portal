{{--
    Formatierten Text aus dem Editor anzeigen.

    Der Inhalt wird hier immer bereinigt (App\Support\RichText) - nie selbst
    mit {!! !!} ausgeben. Die Typografie steht in resources/css/app.css
    (.rich-text) und gilt genauso im Editor.

    Verwendung:
        <x-ui.rich-text :html="$competition->analysis_text" />
--}}
@props(['html' => null])

<div {{ $attributes->merge(['class' => 'rich-text']) }}>{!! \App\Support\RichText::sanitize($html) !!}</div>
