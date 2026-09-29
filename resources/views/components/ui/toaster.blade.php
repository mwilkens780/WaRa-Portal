{{--
    Ausgabe fuer $toast(...) - steht einmal im Layout. Mobil ueber der
    unteren Navigation, am Desktop unten rechts. aria-live kuendigt neue
    Meldungen an, ohne den Fokus zu verschieben.
--}}
<div x-data aria-live="polite" aria-atomic="false"
     class="fixed z-[130] inset-x-4 bottom-20 lg:bottom-4 sm:inset-x-auto sm:right-4 sm:w-96 space-y-2 pointer-events-none">
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div x-transition.opacity
             :role="t.type === 'error' ? 'alert' : 'status'"
             :class="{
                'bg-gray-900 text-white': t.type === 'success' || t.type === 'info',
                'bg-red-700 text-white': t.type === 'error',
                'bg-amber-600 text-white': t.type === 'warning',
             }"
             class="pointer-events-auto flex items-start gap-3 rounded-xl shadow-lg px-4 py-3 text-sm">
            <span class="flex-1" x-text="t.message"></span>
            <template x-if="t.action">
                <button type="button" @click="$store.toasts.runAction(t)"
                        class="font-semibold underline underline-offset-2 hover:no-underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded"
                        x-text="t.action.label"></button>
            </template>
            <button type="button" @click="$store.toasts.dismiss(t.id)" aria-label="Meldung schließen"
                    class="-mr-1 opacity-80 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>
    </template>
</div>
