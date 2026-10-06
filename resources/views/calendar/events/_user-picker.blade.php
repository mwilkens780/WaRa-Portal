{{--
    Durchsuchbare Mehrfachauswahl von Personen (Checkboxen).
    Erwartet: $name (Feldname ohne []), $label, $people (Collection mit id, firstname, lastname), optional $hint
--}}
@php
    $pickerId = 'pick-' . $name;
    $selected = collect(old($name, []))->map(fn($v) => (int) $v)->all();
@endphp
<fieldset x-data="{ q: '' }" class="space-y-2">
    <legend class="block text-sm font-medium text-gray-800">{{ $label }}</legend>
    @isset($hint)<p class="text-xs text-gray-600">{{ $hint }}</p>@endisset
    <input type="search" x-model="q" aria-label="{{ $label }} durchsuchen" placeholder="Name suchen …"
           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
    <div class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-100" id="{{ $pickerId }}">
        @foreach($people as $p)
            @php $pName = trim($p->lastname . ', ' . $p->firstname, ', '); @endphp
            <label class="flex items-center gap-3 px-3 py-2 text-sm text-gray-800 hover:bg-gray-50 cursor-pointer"
                   x-show="!q || @js(mb_strtolower($pName)).includes(q.toLowerCase())">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $p->id }}" @checked(in_array($p->id, $selected, true))
                       class="rounded border-gray-300 text-primary focus:ring-primary/30">
                <span>{{ $pName }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
