@extends('layouts.app')
@section('title', 'Trainingsgruppen')
@section('page-title', 'Trainingsgruppen')

@section('content')
<div class="mt-2 space-y-4">

    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-500">{{ $groups->count() }} {{ $groups->count() === 1 ? 'Gruppe' : 'Gruppen' }}</p>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.training-groups.create') }}"
               class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary-dark transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Neue Gruppe
            </a>
        @endif
    </div>

    @if($groups->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <p class="text-gray-400 text-sm">Noch keine Trainingsgruppen vorhanden.</p>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.training-groups.create') }}" class="mt-4 inline-block text-primary text-sm hover:underline">Erste Gruppe anlegen</a>
            @endif
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($groups as $group)
                @php $colors = $group->colorDots; @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 {{ $colors['border'] }} border-l-4 hover:shadow-md transition-shadow">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-3 h-3 rounded-full {{ $colors['dot'] }} flex-shrink-0"></span>
                                <h3 class="font-semibold text-gray-800 truncate">{{ $group->name }}</h3>
                                @if($group->has_missing_trainer)
                                    @include('partials.no-trainer-badge')
                                @endif
                            </div>
                            @if(!$group->active)
                                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full flex-shrink-0">Inaktiv</span>
                            @endif
                        </div>

                        @if($group->description)
                            <p class="text-xs text-gray-500 mb-3 line-clamp-2">{{ $group->description }}</p>
                        @endif

                        <div class="flex items-center gap-4 text-xs text-gray-500 mb-4">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $group->trainers_count }} {{ $group->trainers_count === 1 ? 'Trainer' : 'Trainer' }}
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $group->swimmers_count }} Schwimmer
                            </span>
                        </div>

                        @if($group->trainers->isNotEmpty())
                            <div class="flex flex-wrap gap-1 mb-4">
                                @foreach($group->trainers->take(3) as $trainer)
                                    <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">{{ $trainer->firstname }} {{ $trainer->lastname }}</span>
                                @endforeach
                                @if($group->trainers->count() > 3)
                                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">+{{ $group->trainers->count() - 3 }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="flex items-center gap-2 pt-3 border-t border-gray-50">
                            <a href="{{ route('admin.training-groups.show', $group) }}"
                               class="flex-1 text-center text-xs font-medium text-primary hover:bg-primary/5 py-1.5 rounded-lg transition-colors">
                                Details
                            </a>
                            <a href="{{ route('admin.training-groups.edit', $group) }}"
                               class="flex-1 text-center text-xs font-medium text-gray-600 hover:bg-gray-50 py-1.5 rounded-lg transition-colors">
                                Bearbeiten
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
