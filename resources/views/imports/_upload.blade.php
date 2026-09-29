{{--
    Upload-Formular (Schritt 1) aus der Beschreibung in App\Support\ImportCatalog.
    Gleich im Import-Center und am Objekt (z. B. Rekordseite).

    @include('imports._upload', ['import' => ImportCatalog::get('records'), 'layout' => 'inline'])
--}}
@php $up = $import['upload']; @endphp
<x-ui.upload-form :action="route($up['action'])" :layout="$layout ?? 'stack'">
    @foreach($up['fields'] ?? [] as $field)
        <x-ui.field as="select" :name="$field['name']" :label="$field['label']" required class="{{ ($layout ?? 'stack') === 'inline' ? 'sm:max-w-xs' : '' }}">
            @foreach($field['options'] as $value => $text)
                <option value="{{ $value }}" @selected(old($field['name'], $field['default'] ?? null) === $value)>{{ $text }}</option>
            @endforeach
        </x-ui.field>
    @endforeach
    <x-ui.file-drop :name="$up['name']" :label="$up['label']" :accept="$up['accept']" :max-mb="$up['max_mb']" :hint="$up['hint'] ?? null" />
</x-ui.upload-form>
