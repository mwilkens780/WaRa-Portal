{{--
    Reiterleiste, gebunden an eine Alpine-Variable der umgebenden Komponente.

    <div x-data="{ tab: 'vr' }">
        <x-ui.tabs model="tab" :tabs="['vr' => 'Vereinsrekorde', 'lr' => 'Landesrekorde']" />
        <div x-show="tab === 'vr'" role="tabpanel" id="panel-vr" aria-labelledby="tab-vr">…</div>
    </div>

    Tastatur: Pfeil links/rechts, Pos1/Ende (WAI-ARIA Tabs). Mobil scrollbar,
    ein Verlauf am Rand zeigt, dass es weitergeht.
    tabs-Werte duerfen auch ['label' => ..., 'count' => ...] sein.
--}}
@props(['model', 'tabs', 'idPrefix' => ''])

<div class="relative border-b border-gray-200" {{ $attributes }}>
    <div role="tablist" class="flex overflow-x-auto -mb-px"
         x-on:keydown="(e) => {
             const t = [...$el.querySelectorAll('[role=tab]')];
             const i = t.indexOf(document.activeElement);
             const n = { ArrowRight: (i + 1) % t.length, ArrowLeft: (i - 1 + t.length) % t.length, Home: 0, End: t.length - 1 }[e.key];
             if (n === undefined || i < 0) return;
             e.preventDefault(); t[n].focus(); t[n].click();
         }">
        @foreach($tabs as $key => $tab)
            @php
                $label = is_array($tab) ? $tab['label'] : $tab;
                $count = is_array($tab) ? ($tab['count'] ?? null) : null;
            @endphp
            <button type="button" role="tab" id="{{ $idPrefix }}tab-{{ $key }}" aria-controls="{{ $idPrefix }}panel-{{ $key }}"
                    @click="{{ $model }} = @js((string) $key)"
                    :aria-selected="{{ $model }} === @js((string) $key) ? 'true' : 'false'"
                    :tabindex="{{ $model }} === @js((string) $key) ? 0 : -1"
                    :class="{{ $model }} === @js((string) $key) ? 'border-primary text-primary' : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300'"
                    class="flex items-center gap-2 whitespace-nowrap border-b-2 px-4 min-h-[44px] text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                {{ $label }}
                @if($count !== null)
                    <span class="rounded-full bg-gray-100 px-1.5 text-xs text-gray-700">{{ $count }}</span>
                @endif
            </button>
        @endforeach
    </div>
    {{-- Hinweis, dass die Leiste mobil weitergeht --}}
    <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-white sm:hidden" aria-hidden="true"></div>
</div>
