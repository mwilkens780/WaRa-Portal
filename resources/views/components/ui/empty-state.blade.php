{{--
    Leerzustand: sagt, warum hier nichts ist - und was man tun kann.

    <x-ui.empty-state icon="calendar" title="Noch keine Einheiten" text="Lege die erste Trainingseinheit an.">
        <x-ui.button href="{{ route('trainer.sessions.create') }}" icon="plus">Neue Einheit</x-ui.button>
    </x-ui.empty-state>
--}}
@props(['icon' => 'inbox', 'title', 'text' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center px-6 py-10']) }}>
    <span class="rounded-full bg-gray-100 p-3 text-gray-500">
        <x-ui.icon :name="$icon" class="w-6 h-6" />
    </span>
    <p class="mt-3 text-sm font-semibold text-gray-900">{{ $title }}</p>
    @if($text)
        <p class="mt-1 text-sm text-gray-600 max-w-sm">{{ $text }}</p>
    @endif
    @if(trim($slot))
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
