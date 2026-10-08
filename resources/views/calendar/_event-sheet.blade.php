{{--
    Detail-Sheet fuer einen oder mehrere Termine eines Tages. Gefuellt ueber
    showEvents(datum, [termine]) der Kalender-Komponente.
--}}
<x-ui.dialog name="calendar-events" title="Termin" size="md">
    <p class="text-sm font-semibold text-gray-800 mb-3" x-text="sheetDate"></p>
    <ul class="space-y-3">
        <template x-for="(e, i) in sheetEvents" :key="i">
            <li class="rounded-xl border border-gray-100 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900" x-text="e.title"></p>
                        <p class="text-sm text-gray-700 mt-0.5" x-show="e.time" x-text="e.time + ' Uhr'"></p>
                        <p class="text-sm text-gray-600 mt-0.5" x-show="e.sub" x-text="e.sub"></p>
                    </div>
                    <span class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700" x-text="e.category"></span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2" x-show="e.url || e.editUrl || e.icsUrl">
                    <template x-if="e.url">
                        <a :href="e.url" class="inline-flex max-w-full items-center justify-center gap-2 min-h-[44px] sm:min-h-[36px] px-3 py-2 text-center leading-snug rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2" x-text="e.urlLabel || 'Öffnen'"></a>
                    </template>
                    <template x-if="e.icsUrl">
                        <a :href="e.icsUrl" class="inline-flex max-w-full items-center justify-center gap-2 min-h-[44px] sm:min-h-[36px] px-3 py-2 text-center leading-snug rounded-lg border border-gray-300 text-gray-800 text-sm font-semibold hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">In meinen Kalender</a>
                    </template>
                    <template x-if="e.editUrl">
                        <a :href="e.editUrl" class="inline-flex items-center justify-center gap-2 min-h-[44px] sm:min-h-[36px] px-3 rounded-lg border border-gray-300 text-gray-800 text-sm font-semibold hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">Bearbeiten</a>
                    </template>
                </div>
            </li>
        </template>
    </ul>
</x-ui.dialog>
