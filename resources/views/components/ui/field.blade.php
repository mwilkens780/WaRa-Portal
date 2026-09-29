{{--
    Formularfeld mit Beschriftung, Hilfetext und Fehlermeldung - alles
    verknuepft (for/id, aria-describedby, aria-invalid), alten Wert und
    Validierungsfehler holt das Feld selbst.

    <x-ui.field label="Vorname" name="firstname" :value="$user->firstname" required />
    <x-ui.field label="Typ" name="type" as="select">
        <option value="training">Training</option>
    </x-ui.field>
    <x-ui.field label="Notizen" name="notes" as="textarea" rows="3" hint="Nur für Trainer sichtbar" />

    Weitere Attribute (placeholder, x-model, min, step ...) gehen ans Feld.
--}}
@props([
    'label',
    'name',
    'as'       => 'input',
    'type'     => 'text',
    'value'    => null,
    'hint'     => null,
    'required' => false,
    'id'       => null,
])

@php
    $id       = $id ?? 'f-' . \Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name));
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $error    = $errors->first($errorKey);
    $describe = trim(($hint ? "{$id}-hint " : '') . ($error ? "{$id}-error" : ''));
    $current  = old($errorKey, $value);
    $control  = 'block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 placeholder-gray-500 bg-white '
        . 'focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-h-[44px] sm:min-h-[40px] '
        . ($error ? 'border-red-400' : 'border-gray-300');
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-800">
        {{ $label }}@if($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif
    </label>

    @if($as === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @if($required) required @endif
                @if($describe) aria-describedby="{{ $describe }}" @endif @if($error) aria-invalid="true" @endif
                {{ $attributes->except('class')->merge(['class' => $control]) }}>
            {{ $slot }}
        </select>
    @elseif($as === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" @if($required) required @endif
                  @if($describe) aria-describedby="{{ $describe }}" @endif @if($error) aria-invalid="true" @endif
                  {{ $attributes->except('class')->merge(['class' => $control, 'rows' => 3]) }}>{{ $current }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}" @if($required) required @endif
               @if($describe) aria-describedby="{{ $describe }}" @endif @if($error) aria-invalid="true" @endif
               {{ $attributes->except('class')->merge(['class' => $control]) }}>
    @endif

    @if($hint)
        <p id="{{ $id }}-hint" class="text-xs text-gray-600">{{ $hint }}</p>
    @endif
    @if($error)
        <p id="{{ $id }}-error" class="text-xs font-medium text-red-700">{{ $error }}</p>
    @endif
</div>
