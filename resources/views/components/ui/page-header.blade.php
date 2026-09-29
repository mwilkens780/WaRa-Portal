{{--
    Kopf einer Seite unter der Titelleiste: Zurueck-Link, Untertitel, Aktionen.
    Der Seitentitel selbst steht in der Titelleiste (@section('page-title')).

    <x-ui.page-header :back="route('parent.dashboard')" back-label="Übersicht" subtitle="Saison 2026/27">
        <x-slot:actions>
            <x-ui.button icon="plus" href="...">Neu</x-ui.button>
        </x-slot:actions>
        <x-slot:menu> … weitere, seltene Aktionen (x-ui.menu) … </x-slot:menu>
    </x-ui.page-header>

    Regel: hoechstens EINE Primaeraktion; Zerstoerendes gehoert ins Menue.
--}}
@props(['back' => null, 'backLabel' => 'Zurück', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 mb-4']) }}>
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" class="inline-flex items-center gap-1 -ml-1 px-1 py-1 text-sm text-gray-600 hover:text-primary rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <x-ui.icon name="chevron-left" class="w-4 h-4" />{{ $backLabel }}
            </a>
        @endif
        @if($subtitle)
            <p class="text-sm text-gray-600">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions) || isset($menu))
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions ?? '' }}
            {{ $menu ?? '' }}
        </div>
    @endif
</div>
