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

<div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">

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
                $navLink = fn(string $route, string $pattern) =>
                    request()->routeIs($pattern)
                        ? 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold bg-white text-[#1B5EAB] shadow-sm'
                        : 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/90 hover:bg-white/10 transition-colors';
            @endphp

            @if($role === 'admin')
                <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pt-3 pb-1.5">Administration</p>

                <a href="{{ route('admin.dashboard') }}" class="{{ $navLink('admin.dashboard','admin.dashboard') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-2a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('admin.users.index') }}" class="{{ $navLink('admin.users.index','admin.users.*') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Benutzerverwaltung</span>
                </a>
                <a href="{{ route('admin.competitions.index') }}" class="{{ $navLink('admin.competitions.index','admin.competitions.*') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Wettkämpfe</span>
                </a>

                <div class="border-t border-white/15 my-3 mx-1"></div>
                <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pb-1.5">Trainer-Bereich</p>

                <a href="{{ route('trainer.sessions.index') }}" class="{{ $navLink('trainer.sessions.index','trainer.sessions.*') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Trainingseinheiten</span>
                </a>
                <a href="{{ route('trainer.dsv-import.index') }}" class="{{ $navLink('trainer.dsv-import.index','trainer.dsv-import.*') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>DSV-Import</span>
                </a>

            @elseif($role === 'trainer')
                <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pt-3 pb-1.5">Trainer</p>

                <a href="{{ route('trainer.dashboard') }}" class="{{ $navLink('trainer.dashboard','trainer.dashboard') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-2a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('trainer.sessions.index') }}" class="{{ $navLink('trainer.sessions.index','trainer.sessions.*') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Trainingseinheiten</span>
                </a>
                <a href="{{ route('trainer.dsv-import.index') }}" class="{{ $navLink('trainer.dsv-import.index','trainer.dsv-import.*') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>DSV-Import</span>
                </a>

            @elseif($role === 'schwimmer')
                <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pt-3 pb-1.5">Mein Bereich</p>

                <a href="{{ route('swimmer.dashboard') }}" class="{{ $navLink('swimmer.dashboard','swimmer.dashboard') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-2a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1v-6z"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('swimmer.times') }}" class="{{ $navLink('swimmer.times','swimmer.times') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Meine Zeiten</span>
                </a>
                <a href="{{ route('swimmer.competitions') }}" class="{{ $navLink('swimmer.competitions','swimmer.competitions') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Wettkämpfe</span>
                </a>

            @elseif($role === 'elternteil')
                <p class="text-[10px] font-bold text-blue-200/70 uppercase tracking-widest px-3 pt-3 pb-1.5">Eltern-Bereich</p>

                <a href="{{ route('parent.dashboard') }}" class="{{ $navLink('parent.dashboard','parent.dashboard') }}">
                    <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>Meine Kinder</span>
                </a>
            @endif
        </nav>

        {{-- User footer --}}
        <div class="border-t border-white/15 p-2 space-y-0.5">
            <a href="{{ route('password.change') }}"
               class="{{ request()->routeIs('password.change') ? 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold bg-white text-[#1B5EAB] shadow-sm' : 'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-white/80 hover:bg-white/10 transition-colors' }}">
                <svg class="w-[18px] h-[18px] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                <span>Passwort ändern</span>
            </a>

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
</div>

@stack('scripts')
</body>
</html>
