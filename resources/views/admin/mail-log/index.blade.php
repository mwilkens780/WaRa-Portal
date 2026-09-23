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

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    {{-- Zahlen --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap gap-6 text-sm">
            @foreach(\App\Models\MailMessage::STATUS_LABELS as $key => $label)
                <div>
                    <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">{{ $label }}</p>
                    <p class="font-semibold text-gray-800 mt-0.5">{{ $counts[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
            <select name="status" class="px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">alle</option>
                @foreach(\App\Models\MailMessage::STATUS_LABELS as $key => $label)
                    <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Thema</label>
            <select name="topic" class="px-3 py-2 border border-gray-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-primary/30">
                <option value="">alle</option>
                @foreach($topics as $key => $topic)
                    <option value="{{ $key }}" {{ $filters['topic'] === $key ? 'selected' : '' }}>{{ $topic['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-700 mb-1">Suche</label>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Adresse oder Betreff"
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
            <p class="px-5 py-12 text-center text-sm text-gray-400">Keine Einträge.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm table-fixed min-w-[900px]">
                <colgroup>
                    <col class="w-36"><col class="w-56"><col class="w-40"><col><col class="w-28"><col class="w-24">
                </colgroup>
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="text-left px-4 py-3">Zeitpunkt</th>
                        <th class="text-left px-4 py-3">Empfänger</th>
                        <th class="text-left px-4 py-3">Thema</th>
                        <th class="text-left px-4 py-3">Betreff / Hinweis</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($messages as $message)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ ($message->sent_at ?? $message->created_at)?->deBerlin('d.m.Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-gray-800 truncate" title="{{ $message->recipient_email }}">
                                    {{ $message->recipient_name ?? $message->recipient_email }}
                                </p>
                                <p class="text-xs text-gray-400 truncate">{{ $message->recipient_email }}</p>
                                @if($message->wasRedirected())
                                    <p class="text-xs text-amber-600 mt-0.5">umgeleitet an {{ $message->sent_to }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ $message->topicLabel() }}</td>
                            <td class="px-4 py-3">
                                <p class="text-gray-700 truncate" title="{{ $message->subject }}">{{ $message->subject }}</p>
                                @if($message->error)
                                    <p class="text-xs text-red-600 mt-0.5">{{ \Illuminate\Support\Str::limit($message->error, 140) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $message->statusBadge() }}">
                                    {{ $message->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($message->status === 'failed' && $message->mailable)
                                    <form method="POST" action="{{ route('admin.mail-log.retry', $message) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-primary hover:underline">erneut senden</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100">{{ $messages->links() }}</div>
        @endif
    </div>
</div>
@endsection
