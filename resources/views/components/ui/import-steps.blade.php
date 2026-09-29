{{--
    Schrittanzeige des Import-Assistenten: Datei wählen → Prüfen → Übernehmen.

    <x-ui.import-steps :current="2" />

    Jeder Import hat dieselben drei Schritte - auch die, deren dritter Schritt
    nur eine Erfolgsmeldung ist. So weiss man ueberall, wo man steht und dass
    vor dem Pruefen noch nichts gespeichert wird.
--}}
@props([
    'current' => 1,
    'steps'   => ['Datei wählen', 'Prüfen', 'Übernehmen'],
])

<ol {{ $attributes->merge(['class' => 'flex items-center gap-2 text-sm']) }} aria-label="Import-Schritte">
    @foreach($steps as $i => $step)
        @php $n = $i + 1; $state = $n < $current ? 'done' : ($n === $current ? 'current' : 'todo'); @endphp
        <li class="flex items-center gap-2 {{ $state === 'todo' ? 'text-gray-600' : 'text-gray-900' }}"
            @if($state === 'current') aria-current="step" @endif>
            <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold flex-shrink-0
                         {{ $state === 'done' ? 'bg-green-600 text-white' : ($state === 'current' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700') }}"
                  aria-hidden="true">
                @if($state === 'done')✓@else{{ $n }}@endif
            </span>
            <span class="{{ $state === 'current' ? 'font-semibold' : '' }} {{ $state !== 'current' ? 'hidden sm:inline' : '' }}">
                {{ $step }}@if($state === 'done')<span class="sr-only"> (erledigt)</span>@endif
            </span>
            @if(!$loop->last)
                <span aria-hidden="true" class="w-6 sm:w-10 h-px bg-gray-300"></span>
            @endif
        </li>
    @endforeach
</ol>
