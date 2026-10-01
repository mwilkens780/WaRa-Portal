@extends('layouts.app')
@section('title', 'Mail-Protokoll')
@section('page-title', 'Mail-Protokoll')

@section('content')
<div class="mt-2 space-y-5">

    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zu den Einstellungen
    </a>


    {{-- Zahlen --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap gap-6 text-sm">
            @foreach(\App\Models\MailMessage::STATUS_LABELS as $key => $label)
                <div>
                    <p class="text-xs text-gray-600 font-medium uppercase tracking-wide">{{ $label }}</p>
                    <p class="font-semibold text-gray-800 mt-0.5">{{ $counts[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
            <select aria-label="Status" name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">alle</option>
                @foreach(\App\Models\MailMessage::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Thema</label>
            <select aria-label="Thema" name="topic" class="px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">alle</option>
                @foreach($topics as $key => $topic)
                    <option value="{{ $key }}" {{ $filters['topic'] === $key ? 'selected' : '' }}>{{ $topic['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-700 mb-1">Suche</label>
            <input aria-label="Suche" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Adresse oder Betreff"
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
        </div>
        <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark transition-colors">
            Filtern
        </button>
        <a href="{{ route('admin.mail-log.index') }}" class="px-4 py-2 border border-gray-200 text-gray-600 text-sm rounded-lg hover:bg-gray-50">
            Zurücksetzen
        </a>
    </form>

    {{-- Liste --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if($messages->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-gray-600">Keine Einträge.</p>
        @else
        <x-ui.table :card="false" caption="Mailprotokoll" stack class="table-fixed min-w-[900px]"
            :columns="[['label' => 'Zeitpunkt', 'class' => 'w-36'], ['label' => 'Empfänger', 'class' => 'w-56'], ['label' => 'Thema', 'class' => 'w-40'], 'Betreff / Hinweis', ['label' => 'Status', 'class' => 'w-28'], ['label' => 'Aktionen', 'sr' => true, 'class' => 'w-24']]">
                    @foreach($messages as $message)
                        <tr class="hover:bg-gray-50 align-top">
                            <x-ui.td label="Zeitpunkt" muted class="whitespace-nowrap">
                                {{ ($message->sent_at ?? $message->created_at)?->deBerlin('d.m.Y H:i') }}
                            </x-ui.td>
                            <x-ui.td label="Empfänger">
                                <p class="text-gray-800 truncate" title="{{ $message->recipient_email }}">
                                    {{ $message->recipient_name ?? $message->recipient_email }}
                                </p>
                                <p class="text-xs text-gray-600 truncate">{{ $message->recipient_email }}</p>
                                @if($message->wasRedirected())
                                    <p class="text-xs text-amber-700 mt-0.5">umgeleitet an {{ $message->sent_to }}</p>
                                @endif
                            </x-ui.td>
                            <x-ui.td label="Thema" muted class="text-xs">{{ $message->topicLabel() }}</x-ui.td>
                            <x-ui.td label="Betreff / Hinweis">
                                <p class="text-gray-700 truncate" title="{{ $message->subject }}">{{ $message->subject }}</p>
                                @if($message->error)
                                    <p class="text-xs text-red-600 mt-0.5">{{ \Illuminate\Support\Str::limit($message->error, 140) }}</p>
                                @endif
                            </x-ui.td>
                            <x-ui.td label="Status">
                                <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $message->statusBadge() }}">
                                    {{ $message->statusLabel() }}
                                </span>
                            </x-ui.td>
                            <x-ui.td align="right">
                                @if($message->status === 'failed' && $message->mailable)
                                    <form method="POST" action="{{ route('admin.mail-log.retry', $message) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-primary hover:underline">erneut senden</button>
                                    </form>
                                @endif
                            </x-ui.td>
                        </tr>
                    @endforeach
                </x-ui.table>
        <div class="px-5 py-4 border-t border-gray-100">{{ $messages->links() }}</div>
        @endif
    </div>
</div>
@endsection
