<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WaRa-Portal') – SG Wasserratten Norderstedt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#1B5EAB',
                            700: '#1d4ed8',
                            800: '#0D3F7A',
                            900: '#1e3a5f',
                            DEFAULT: '#1B5EAB',
                        },
                        accent: {
                            DEFAULT: '#C0392B',
                            dark: '#992d22',
                            light: '#e74c3c',
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 min-h-screen">

<div x-data="{ sidebarOpen: false, pwModal: {{ $errors->has('current_password') || $errors->has('password') ? 'true' : 'false' }}, pwLoading: false }" class="flex h-screen overflow-hidden">

    {{-- Sidebar --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 w-60 bg-[#1B5EAB] text-white flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto"
    >
        {{-- Logo & Vereinsname --}}
        <div class="flex items-center gap-3 px-4 py-4 border-b border-white/15">
            <img src="https://www.wasserratten.de/images/logo96x96.png"
                 alt="Logo"
                 class="w-10 h-10 rounded-full bg-white/90 p-0.5 flex-shrink-0">
            <div class="min-w-0">
                <p class="font-bold text-sm leading-snug">SG Wasserratten</p>
                <p class="text-xs text-blue-200 leading-snug">Norderstedt e.V.</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
            @php
                $role = auth()->user()->role;
                $can  = fn(string $k) => \App\Models\MenuPermission::can($role, $k);
                $cls  = fn(string $pat) => request()->routeIs($pat)
                    ? 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold bg-white text-[#1B5EAB] shadow-sm'
                    : 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/90 hover:bg-white/10 transition-colors';

                $dashUrl = match($role) {
                    'admin'      => route('admin.dashboard'),
                    'trainer'    => route('trainer.dashboard'),
                    'schwimmer'  => route('swimmer.dashboard'),
                    'elternteil' => route('parent.dashboard'),
                    default      => route('calendar.index'),
                };
                $dashPat = match($role) {
                    'admin'      => 'admin.dashboard',
                    'trainer'    => 'trainer.dashboard',
                    'schwimmer'  => 'swimmer.dashboard',
                    'elternteil' => 'parent.dashboard',
                    default      => '_none_',
                };

                $ti = [
                    'training'        => in_array($role, ['trainer','admin']) && $can('training'),
                    'training_groups' => in_array($role, ['trainer','admin']) && $can('training_groups'),
                    'competitions'    => in_array($role, ['trainer','admin','vorstand','kampfrichter']) && $can('competitions'),
                    'records'         => in_array($role, ['trainer','admin','vorstand']) && $can('records'),
                    'goals'           => in_array($role, ['trainer','admin']) && $can('goals'),
                    'hall'            => in_array($role, ['trainer','admin']) && $can('hall'),
                ];
                $showTrainerSection = in_array(true, $ti, true);
                $showCalendar       = $can('calendar');
                $showUsersLite      = $role !== 'admin' && $can('users_lite');
            @endphp

            {{-- Dashboard (immer zuerst) --}}
            <a href="{{ $dashUrl }}" class="{{ $cls($dashPat) }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-2a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/></svg>
                <span>Dashboard</span>
            </a>

            {{-- Administration (nur Admin) --}}
            @if($role === 'admin')
            <div class="border-t border-white/15 my-3 mx-1"></div>
            <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pb-1.5">Administration</p>

            <a href="{{ route('admin.users.index') }}" class="{{ $cls('admin.users.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Benutzerverwaltung</span>
            </a>
            <a href="{{ route('admin.permissions.index') }}" class="{{ $cls('admin.permissions.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>Berechtigungs-Matrix</span>
            </a>
            <a href="{{ route('admin.logs.index') }}" class="{{ $cls('admin.logs.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Protokoll</span>
            </a>
            <a href="{{ route('admin.settings.index') }}" class="{{ $cls('admin.settings.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Einstellungen</span>
            </a>
            @endif

            {{-- Trainer-Bereich (Trainer, Admin, Vorstand/Kampfrichter je nach Berechtigung) --}}
            @if($showTrainerSection)
            <div class="border-t border-white/15 my-3 mx-1"></div>
            <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pb-1.5">Trainer-Bereich</p>

            @if($ti['training'])
            <a href="{{ route('trainer.sessions.index') }}" class="{{ $cls('trainer.sessions.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Trainingseinheiten</span>
            </a>
            @endif

            @if($ti['training_groups'])
            <a href="{{ route('admin.training-groups.index') }}" class="{{ $cls('admin.training-groups.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Trainingsgruppen</span>
            </a>
            @endif

            @if($ti['competitions'])
            <a href="{{ route('admin.competitions.index') }}" class="{{ $cls('admin.competitions.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Wettkämpfe</span>
            </a>
            @endif

            @if($ti['records'])
            <a href="{{ route('admin.records.index') }}" class="{{ $cls('admin.records.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                <span>Rekorde</span>
            </a>
            @endif

            @if($ti['goals'])
            <a href="{{ route('trainer.goals.index') }}" class="{{ $cls('trainer.goals.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Ziele</span>
            </a>
            @endif

            @if($ti['hall'])
            <a href="{{ route('trainer.hall.index') }}" class="{{ $cls('trainer.hall.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Hallenbelegung</span>
            </a>
            @endif
            @endif

            {{-- Allgemein: Kalender + Benutzerverwaltung (Lite) --}}
            @if($showCalendar || $showUsersLite)
            <div class="border-t border-white/15 my-3 mx-1"></div>
            <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pb-1.5">Allgemein</p>

            @if($showCalendar)
            <a href="{{ route('calendar.index') }}" class="{{ $cls('calendar.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Kalender</span>
            </a>
            @endif

            @if($showUsersLite)
            <a href="{{ route('users-lite.index') }}" class="{{ $cls('users-lite.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Benutzerverwaltung</span>
            </a>
            @endif
            @endif

            {{-- Schwimmer --}}
            @if($role === 'schwimmer')
            <div class="border-t border-white/15 my-3 mx-1"></div>
            <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pb-1.5">Mein Bereich</p>

            @if($can('swimmer_times'))
            <a href="{{ route('swimmer.times') }}" class="{{ $cls('swimmer.times') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Meine Bestzeiten</span>
            </a>
            @endif

            @if($can('swimmer_comps'))
            <a href="{{ route('swimmer.competitions') }}" class="{{ $cls('swimmer.competitions') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Meine Wettkämpfe</span>
            </a>
            @endif

            @if($can('swimmer_goals'))
            <a href="{{ route('swimmer.goals.index') }}" class="{{ $cls('swimmer.goals.*') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Meine Ziele</span>
            </a>
            @endif

            <a href="{{ route('swimmer.sessions') }}" class="{{ $cls('swimmer.sessions') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Mein Training</span>
            </a>
            @endif

            {{-- Elternteil --}}
            @if($role === 'elternteil' && $can('parent_area'))
            <div class="border-t border-white/15 my-3 mx-1"></div>
            <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pb-1.5">Eltern-Bereich</p>

            <a href="{{ route('parent.dashboard') }}" class="{{ $cls('parent.dashboard') }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Meine Kinder</span>
            </a>
            @endif
        </nav>

        {{-- User footer --}}
        <div class="border-t border-white/15 p-2 space-y-0.5">
            <button type="button" @click="pwModal = true; sidebarOpen = false"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:bg-white/10 transition-colors">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                <span>Passwort ändern</span>
            </button>

            <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-white/5">
                <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold flex-shrink-0">
                    {{ strtoupper(substr(auth()->user()->firstname ?: auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white leading-snug truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-blue-200/80 leading-snug">{{ auth()->user()->role_label }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:bg-red-500/20 hover:text-white transition-colors">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>Abmelden</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Overlay Mobile --}}
    <div x-show="sidebarOpen" x-cloak
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/50 z-40 lg:hidden"></div>

    {{-- Main Content --}}
    <div class="flex-1 flex flex-col min-h-screen overflow-y-auto">

        {{-- Top Bar --}}
        <header class="bg-white shadow-sm sticky top-0 z-30 flex items-center justify-between px-4 py-3 lg:px-6">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = !sidebarOpen"
                        class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-lg font-semibold text-gray-800">@yield('page-title', 'WaRa-Portal')</h1>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ now()->isoFormat('dddd, D. MMMM YYYY') }}
            </div>
        </header>

        {{-- Flash Messages --}}
        <div class="px-4 lg:px-6 pt-4">
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                     class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 mb-4">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 mb-4">
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Page Content --}}
        <main class="flex-1 px-4 lg:px-6 pb-8">
            @yield('content')
        </main>

        <footer class="text-center text-xs text-gray-400 py-4 border-t border-gray-100">
            WaRa-Portal &copy; {{ date('Y') }} – SG Wasserratten Norderstedt e.V.
        </footer>
    </div>

{{-- Passwort-ändern-Modal (global, für alle Rollen) --}}
<div x-show="pwModal" x-cloak
     class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     @keydown.escape.window="pwModal = false">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50" @click="pwModal = false"></div>

    {{-- Dialog --}}
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md"
         @click.stop
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-800">Passwort ändern</h2>
            <button type="button" @click="pwModal = false"
                    class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('password.update') }}"
              @submit="pwLoading = true"
              class="px-6 py-5 space-y-4">
            @csrf
            @method('PUT')

            @if($errors->has('current_password') || $errors->has('password'))
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
                    @foreach(array_filter([$errors->first('current_password'), $errors->first('password')]) as $err)
                        <p>{{ $err }}</p>
                    @endforeach
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Aktuelles Passwort</label>
                <input type="password" name="current_password" required autocomplete="current-password"
                       class="w-full px-4 py-2.5 border {{ $errors->has('current_password') ? 'border-red-400' : 'border-gray-300' }} rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Neues Passwort</label>
                <input type="password" name="password" required autocomplete="new-password"
                       class="w-full px-4 py-2.5 border {{ $errors->has('password') ? 'border-red-400' : 'border-gray-300' }} rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <p class="text-xs text-gray-400 mt-1">Mindestens 8 Zeichen, Buchstaben und Zahlen</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Neues Passwort bestätigen</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        :disabled="pwLoading"
                        class="flex-1 bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 rounded-lg text-sm transition-colors disabled:opacity-60">
                    <span x-show="!pwLoading">Passwort speichern</span>
                    <span x-show="pwLoading" x-cloak>Wird gespeichert…</span>
                </button>
                <button type="button" @click="pwModal = false"
                        class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
            </div>
        </form>
    </div>
</div>
</div>{{-- Ende x-data (sidebarOpen, pwModal, pwLoading) --}}

@stack('scripts')
</body>
</html>
