{{--
    Der eine Bestaetigungsdialog der Seite (steht im Layout).
    Aufruf per $confirm({...}) oder data-confirm - siehe resources/js/ui/confirm.js.

    "Abbrechen" steht zuerst und bekommt den Fokus: Enter auf einer
    Loesch-Rueckfrage soll nie aus Versehen loeschen.
--}}
<div x-data x-show="$store.confirm.open" x-cloak
     class="fixed inset-0 z-[120] flex items-end sm:items-center justify-center sm:p-4"
     role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text">

    <div class="absolute inset-0 bg-gray-900/50" @click="$store.confirm.answer(false)" aria-hidden="true"></div>

    <div x-show="$store.confirm.open"
         x-trap="$store.confirm.open"
         x-transition.opacity.duration.150ms
         @keydown.escape.stop.prevent="$store.confirm.answer(false)"
         class="relative w-full sm:max-w-md bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl p-5"
         style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
        <div class="flex items-start gap-3">
            <span class="flex-shrink-0 mt-0.5 rounded-full p-2"
                  :class="$store.confirm.danger ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-primary'">
                <x-ui.icon name="alert" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
                <h2 id="confirm-title" class="text-base font-semibold text-gray-900" x-text="$store.confirm.title"></h2>
                <p id="confirm-text" class="text-sm text-gray-600 mt-1 whitespace-pre-line" x-show="$store.confirm.text" x-text="$store.confirm.text"></p>
            </div>
        </div>
        <template x-if="$store.confirm.inputLabel">
            <div class="mt-4">
                <label for="confirm-input" class="block text-sm font-medium text-gray-800" x-text="$store.confirm.inputLabel"></label>
                <input id="confirm-input" type="text" x-model="$store.confirm.typed" x-init="$nextTick(() => { $el.focus(); $el.select() })"
                       @keydown.enter.prevent="$store.confirm.answer(true)"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 min-h-[44px] text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
        </template>
        <template x-if="$store.confirm.requireText">
            <div class="mt-4">
                <label for="confirm-require" class="block text-sm text-gray-800">
                    Zur Bestätigung <strong x-text="$store.confirm.requireText"></strong> eingeben:
                </label>
                <input id="confirm-require" type="text" x-model="$store.confirm.typed" autocomplete="off" autocapitalize="characters"
                       @keydown.enter.prevent="$store.confirm.answer(true)"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 min-h-[44px] text-sm focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-red-500">
            </div>
        </template>
        <div class="mt-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            {{-- Rueckfalltexte, solange keine Rueckfrage offen ist (sonst leerer Knopf ohne Namen) --}}
            <x-ui.button variant="secondary" @click="$store.confirm.answer(false)" x-text="$store.confirm.cancelLabel || 'Abbrechen'" class="order-first sm:order-none">Abbrechen</x-ui.button>
            <button type="button" @click="$store.confirm.answer(true)" x-text="$store.confirm.confirmLabel || 'Bestätigen'" :disabled="!$store.confirm.canConfirm"
                    :class="$store.confirm.danger ? 'bg-accent hover:bg-accent-dark' : 'bg-primary hover:bg-primary-dark'"
                    class="inline-flex items-center justify-center min-h-[44px] sm:min-h-[40px] px-4 rounded-lg text-sm font-semibold text-white transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed">Bestätigen</button>
        </div>
    </div>
</div>
