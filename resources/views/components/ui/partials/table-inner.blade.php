{{-- Inneres von x-ui.table (siehe dort) --}}
@php
    $align = ['left' => 'text-left', 'right' => 'text-right', 'center' => 'text-center'];
    $hide  = ['sm' => 'hidden sm:table-cell', 'md' => 'hidden md:table-cell', 'lg' => 'hidden lg:table-cell'];
    $pad   = $dense ? 'px-3 py-1.5' : 'px-4 py-2.5';
    $cols  = collect($columns)->map(fn($c) => is_array($c) ? $c : ['label' => $c]);
@endphp
@if($empty)
    <x-ui.empty-state :icon="$emptyIcon" :title="$emptyTitle" :text="$emptyText" />
@else
    <div class="overflow-x-auto">
        <table {{ $tableAttributes->merge(['class' => 'w-full text-sm' . ($stack ? ' ui-table-stack' : '')]) }}>
            <caption class="sr-only">{{ $caption }}</caption>
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    @if($head)
                        {{ $head }}
                    @else
                        @foreach($cols as $c)
                            <th scope="col" class="{{ $pad }} {{ $align[$c['align'] ?? 'left'] }} {{ $hide[$c['hide'] ?? ''] ?? '' }} {{ $c['class'] ?? '' }} text-xs font-semibold uppercase tracking-wide text-gray-600 whitespace-nowrap">
                                @if($c['sr'] ?? false)<span class="sr-only">{{ $c['label'] }}</span>@else{{ $c['label'] }}@endif
                            </th>
                        @endforeach
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                {{ $rows }}
            </tbody>
            @if($foot)
                <tfoot class="bg-gray-50 border-t border-gray-200">{{ $foot }}</tfoot>
            @endif
        </table>
    </div>
@endif
