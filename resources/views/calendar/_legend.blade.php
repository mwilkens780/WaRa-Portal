<div class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3 mt-1">
    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">
        Legende &ndash; Kategorie anklicken zum Ein-/Ausblenden
    </p>
    <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs">

        @php
            $cats = [
                ['key' => 'blue',    'label' => 'Training',         'dot'  => 'bg-blue-500'],
                ['key' => 'red',     'label' => 'Wettkampf',        'dot'  => 'bg-red-500'],
                ['key' => 'emerald', 'label' => 'Vereinstermin',    'dot'  => 'bg-emerald-500'],
                ['key' => 'amber',   'label' => 'Ehrung',           'dot'  => 'bg-amber-500'],
                ['key' => 'orange',  'label' => 'Meldefrist',       'dot'  => 'bg-orange-500'],
                ['key' => 'purple',  'label' => 'Vorstandssitzung', 'dot'  => 'bg-purple-500'],
                ['key' => 'gray',    'label' => 'Sonstiges',        'dot'  => 'bg-gray-400'],
            ];
            $bgs = [
                ['key' => 'holiday', 'label' => 'Feiertag (SH+HH)', 'bg' => 'bg-green-200'],
                ['key' => 'vacSH',   'label' => 'Schulferien SH',   'bg' => 'bg-sky-200'],
                ['key' => 'vacHH',   'label' => 'Schulferien HH',   'bg' => 'bg-violet-200'],
            ];
        @endphp

        @foreach($cats as $c)
            <button type="button"
                    @click="categories['{{ $c['key'] }}'] = !categories['{{ $c['key'] }}']"
                    :class="categories['{{ $c['key'] }}'] ? 'text-gray-600' : 'opacity-35 line-through text-gray-400'"
                    class="flex items-center gap-1.5 transition-all select-none hover:opacity-75 cursor-pointer">
                <span class="w-2.5 h-2.5 rounded-full {{ $c['dot'] }} flex-shrink-0"
                      :class="categories['{{ $c['key'] }}'] ? '' : 'opacity-40'"></span>
                {{ $c['label'] }}
            </button>
        @endforeach

        <span class="text-gray-200 self-center">|</span>

        @foreach($bgs as $c)
            <button type="button"
                    @click="categories['{{ $c['key'] }}'] = !categories['{{ $c['key'] }}']"
                    :class="categories['{{ $c['key'] }}'] ? 'text-gray-600' : 'opacity-35 line-through text-gray-400'"
                    class="flex items-center gap-1.5 transition-all select-none hover:opacity-75 cursor-pointer">
                <span class="w-3 h-2.5 rounded-sm {{ $c['bg'] }} flex-shrink-0"
                      :class="categories['{{ $c['key'] }}'] ? '' : 'opacity-40'"></span>
                {{ $c['label'] }}
            </button>
        @endforeach

    </div>
</div>
