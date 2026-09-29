{{--
    Liste von Gesundheitsdokumenten einer Person (eigene oder die eines Kindes).
    Erwartet: $title, $subtitle, $documents
--}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
        <svg class="w-5 h-5 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <div>
            <h2 class="text-base font-semibold text-gray-800">{{ $title }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $subtitle }}</p>
        </div>
    </div>

    @if($documents->isEmpty())
    <div class="px-6 py-8 text-center text-sm text-gray-500">Noch keine Dokumente vorhanden.</div>
    @else
    <div class="divide-y divide-gray-50">
        @foreach($documents as $doc)
        <div class="flex items-start justify-between px-6 py-3.5 gap-4">
            <div class="flex items-start gap-3 min-w-0">
                <svg class="w-8 h-8 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-800">{{ $doc->title }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $doc->category === 'nutrition' ? 'Ernährungsberatung' : 'Sportmedizin' }}
                        &nbsp;·&nbsp; {{ $doc->file_size_formatted }}
                        &nbsp;·&nbsp; {{ $doc->created_at->format('d.m.Y') }}
                        @if($doc->uploader) &nbsp;·&nbsp; {{ $doc->uploader->name }} @endif
                    </p>
                    @if($doc->tags)
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($doc->tags as $tag)
                        <span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{{ $tag }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
            <a href="{{ route('health.download', $doc) }}"
               class="flex-shrink-0 flex items-center gap-1.5 px-3 py-2 bg-primary/10 hover:bg-primary/20 text-primary text-xs font-medium rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download<span class="sr-only">: {{ $doc->title }}</span>
            </a>
        </div>
        @endforeach
    </div>
    @endif
</div>
