{{--
    Dialog fuer alle Pop-ups. Mobil als Bottom-Sheet, ab 640 px zentriert.

    <x-ui.dialog name="password" title="Passwort ändern" :show="$errors->has('password')" guard>
        ... Inhalt ...
        <x-slot:footer>
            <x-ui.button variant="secondary" @click="close()">Abbrechen</x-ui.button>
            <x-ui.button type="submit" form="pw-form">Speichern</x-ui.button>
        </x-slot:footer>
    </x-ui.dialog>

    Oeffnen: $dispatch('open-dialog', 'password') bzw. window.openDialog('password')
    Innerhalb des Dialogs: close()

    Barrierefreiheit: role=dialog + aria-modal + Titel verknuepft, Fokus bleibt
    im Dialog (x-trap) und geht danach an den Ausloeser zurueck, Escape
    schliesst nur diesen Dialog, der Rest der Seite ist fuer Screenreader
    per aria-modal ausgeblendet (bewusst nicht x-trap.inert: das versteckt
    auch einen danach geoeffneten Bestaetigungsdialog vor Screenreadern).
    Footer bleibt sichtbar, der Inhalt scrollt.
    guard: Rueckfrage, bevor geaenderte Eingaben verworfen werden.
--}}
@props([
    'name',
    'title',
    'description' => null,
    'size'        => 'md',
    'show'        => false,
    'guard'       => false,
])

@php
    $width = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-lg', 'lg' => 'sm:max-w-2xl', 'xl' => 'sm:max-w-4xl'][$size] ?? 'sm:max-w-lg';
    $id    = 'dlg-' . \Illuminate\Support\Str::slug($name);
@endphp

<div x-data="uiDialog({ name: @js($name), show: @js((bool) $show), guard: @js((bool) $guard) })"
     @open-dialog.window="onOpenEvent($event)"
     @close-dialog.window="onCloseEvent($event)"
     x-show="open" x-cloak
     class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center sm:p-4"
     role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
     @if($description) aria-describedby="{{ $id }}-desc" @endif
     {{ $attributes->only(['id']) }}>

    <div class="absolute inset-0 bg-gray-900/50" @click="close()" aria-hidden="true"
         x-show="open" x-transition.opacity></div>

    <div x-show="open"
         x-trap="open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @keydown.escape.stop.prevent="close()"
         @input="markDirty()"
         style="max-height: 90vh; max-height: 90dvh"
         {{ $attributes->except(['id'])->merge(['class' => "relative flex flex-col w-full {$width} bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl"]) }}>

        <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
            <div class="min-w-0">
                <h2 id="{{ $id }}-title" class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                @if($description)
                    <p id="{{ $id }}-desc" class="text-sm text-gray-600 mt-0.5">{{ $description }}</p>
                @endif
            </div>
            <x-ui.icon-button icon="x" label="Schließen" @click="close()" class="-mr-2 -mt-1" />
        </div>

        <div class="flex-1 overflow-y-auto overscroll-contain px-5 py-4">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="flex flex-wrap items-center justify-end gap-2 px-5 py-3 border-t border-gray-100"
                 style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
