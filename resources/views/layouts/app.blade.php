<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1B5EAB">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-96x96.png') }}">
    @include('partials.pwa-head')
    <title>@yield('title', 'WaRa-Portal') – SG Wasserratten Norderstedt</title>
    {{--
        Absicherung fuer data-confirm, solange app.js noch nicht geladen ist
        (langsames Netz, Ladefehler): dann fragt der Browser selbst nach, statt
        ohne Rueckfrage zu loeschen. Sobald ui/confirm.js bereit ist
        (__uiConfirmReady), uebernimmt der gestaltete Dialog.
    --}}
    <script>
        (function () {
            function ask(el) {
                var d = el.dataset;
                if (d.confirmRequire) {
                    return window.prompt(d.confirm + '\n\nZur Bestätigung ' + d.confirmRequire + ' eingeben:') === d.confirmRequire;
                }
                return window.confirm(d.confirm + (d.confirmText ? '\n\n' + d.confirmText : ''));
            }
            function guard(e, el) {
                if (window.__uiConfirmReady || !el || !el.dataset || !el.dataset.confirm) return;
                if (!ask(el)) { e.preventDefault(); e.stopImmediatePropagation(); }
            }
            document.addEventListener('submit', function (e) { guard(e, e.target); }, true);
            document.addEventListener('click', function (e) {
                guard(e, e.target.closest && e.target.closest('a[data-confirm], button[data-confirm]'));
            }, true);
        })();
    </script>
    {{-- CSS (Tailwind-Build) und JS (Alpine) aus resources/, siehe vite.config.js --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-50 min-h-screen text-gray-900">

{{-- Tastatur-Nutzer springen direkt zum Inhalt statt durch das ganze Menue --}}
<a href="#main"
   class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-1/2 focus:-translate-x-1/2 focus:z-[200] focus:bg-white focus:text-primary focus:font-semibold focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-lg">
    Zum Inhalt springen
</a>

@php
    $authUser = auth()->user();
    // Menue an einer Stelle: App\Support\Navigation (Rollen + Berechtigungs-Matrix)
    $navSections = \App\Support\Navigation::sections($authUser);
    $navAccount  = \App\Support\Navigation::account($authUser);
    $navBottom   = \App\Support\Navigation::bottom($authUser);
    $navLink = fn(bool $active) => $active
        ? 'flex items-center gap-3 px-3 min-h-[44px] lg:min-h-[38px] rounded-lg text-sm font-semibold bg-white text-primary shadow-sm'
        : 'flex items-center gap-3 px-3 min-h-[44px] lg:min-h-[38px] rounded-lg text-sm font-medium text-white/90 hover:bg-white/10 hover:text-white transition-colors';
@endphp

<div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden"
     @keydown.escape.window="sidebarOpen = false">

    {{-- Hintergrund der mobilen Seitenleiste --}}
    <div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"
         class="fixed inset-0 bg-gray-900/50 z-40 lg:hidden" aria-hidden="true"></div>

    {{-- Seitenleiste: am Desktop fest, mobil als Dialog (Fokus bleibt drin, Escape schliesst) --}}
    <aside id="sidebar"
           {{-- Zu ist der Grundzustand ohne JS; Alpine schaltet nur "offen" dazu. Sonst
                steht das Menue mobil offen im Bild, bis Alpine geladen ist. --}}
           :class="sidebarOpen && '!translate-x-0'"
           x-trap.inert="sidebarOpen"
           :role="sidebarOpen ? 'dialog' : null"
           :aria-modal="sidebarOpen ? 'true' : null"
           aria-label="Hauptmenü"
           class="fixed inset-y-0 left-0 z-50 w-64 bg-primary text-white flex flex-col transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0 lg:static lg:inset-auto">

        <div class="flex items-center gap-3 px-4 py-4 border-b border-white/15">
            <img src="{{ asset('images/logo-96x96.png') }}" alt="" class="w-10 h-10 rounded-full bg-white/90 p-0.5 flex-shrink-0">
            <div class="min-w-0 flex-1">
                <p class="font-bold text-sm leading-snug">SG Wasserratten</p>
                <p class="text-xs text-blue-100 leading-snug">Norderstedt e.V.</p>
            </div>
            <x-ui.icon-button icon="x" label="Menü schließen" tone="light" class="lg:hidden -mr-2" @click="sidebarOpen = false" />
        </div>

        <nav aria-label="Hauptnavigation" class="flex-1 overflow-y-auto overscroll-contain py-2 px-2">
            @foreach($navSections as $i => $section)
                @if($section['label'])
                    <p id="nav-sec-{{ $i }}" class="px-3 pt-4 pb-1.5 text-xs font-bold uppercase tracking-wider text-blue-100">{{ $section['label'] }}</p>
                @endif
                <ul class="space-y-0.5" @if($section['label']) aria-labelledby="nav-sec-{{ $i }}" @endif>
                    @foreach($section['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="{{ $navLink($item['active']) }}"
                               @if($item['active']) aria-current="page" @endif>
                                <x-ui.icon :name="$item['icon']" class="w-[18px] h-[18px]" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        {{-- Konto --}}
        {{-- Aufklappbar, damit das Menue mobil nicht vom Konto verdraengt wird --}}
        <div class="border-t border-white/15 p-2 space-y-0.5"
             x-data="{ acc: {{ collect($navAccount)->contains('active', true) ? 'true' : 'false' }} }">
            <button type="button" @click="acc = !acc" :aria-expanded="acc ? 'true' : 'false'" aria-controls="nav-account"
                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-left hover:bg-white/10">
                <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold flex-shrink-0" aria-hidden="true">
                    {{ strtoupper(substr($authUser->firstname ?: $authUser->name, 0, 1)) }}
                </span>
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium text-white leading-snug truncate">{{ $authUser->name }}</span>
                    <span class="block text-xs text-blue-100 leading-snug">{{ $authUser->role_label }} · Konto</span>
                </span>
                <x-ui.icon name="chevron-down" class="w-4 h-4 text-blue-100 transition-transform" ::class="acc ? 'rotate-180' : ''" />
            </button>
            <ul id="nav-account" class="space-y-0.5" x-show="acc" x-cloak>
                @foreach($navAccount as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="{{ $navLink($item['active']) }}" @if($item['active']) aria-current="page" @endif>
                            <x-ui.icon :name="$item['icon']" class="w-[18px] h-[18px]" /><span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
                <li>
                    <button type="button" @click="sidebarOpen = false; $dispatch('open-dialog', 'password')" class="w-full {{ $navLink(false) }}">
                        <x-ui.icon name="key" class="w-[18px] h-[18px]" /><span>Passwort ändern</span>
                    </button>
                </li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full {{ $navLink(false) }} hover:bg-red-500/20">
                            <x-ui.icon name="logout" class="w-[18px] h-[18px]" /><span>Abmelden</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    {{-- Hauptbereich --}}
    <div class="flex-1 flex flex-col min-h-screen overflow-y-auto">

        <header class="bg-white shadow-sm sticky top-0 z-30 flex items-center justify-between gap-3 px-4 py-2.5 lg:px-6">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" @click="sidebarOpen = true"
                        aria-controls="sidebar" :aria-expanded="sidebarOpen ? 'true' : 'false'" aria-label="Menü öffnen"
                        class="lg:hidden -ml-2 inline-flex items-center justify-center w-11 h-11 rounded-lg text-gray-600 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                    <x-ui.icon name="menu" class="w-6 h-6" />
                </button>
                {{-- Lange Titel (Wettkampfnamen) nicht mehrzeilig umbrechen - voller Text im Tooltip --}}
                <h1 class="text-lg font-semibold text-gray-900 truncate" title="@yield('page-title', 'WaRa-Portal')">@yield('page-title', 'WaRa-Portal')</h1>
            </div>
            <div class="flex items-center gap-3 text-sm text-gray-600 flex-shrink-0">
                @if(!empty($appAllSeasons) && $appAllSeasons->count() > 0)
                    <x-ui.menu :label="$appCurrentSeason?->label ?? 'Saison'" :text="true" class="hidden sm:inline-block">
                        @foreach($appAllSeasons as $s)
                            <form method="GET" action="{{ request()->url() }}">
                                <input type="hidden" name="season_id" value="{{ $s->id }}">
                                <x-ui.menu-item type="submit" :active="$appCurrentSeason?->id === $s->id">{{ $s->label }}</x-ui.menu-item>
                            </form>
                        @endforeach
                    </x-ui.menu>
                @endif
                <span class="hidden md:flex items-center gap-1.5">
                    <x-ui.icon name="calendar" class="w-4 h-4" />
                    {{ now()->isoFormat('dddd, D. MMMM YYYY') }}
                </span>
            </div>
        </header>

        {{-- Meldungen: Erfolg/Info als Toast, Fehler/Warnungen bleiben stehen --}}
        <div class="px-4 lg:px-6 pt-4 space-y-3">
            @foreach(['success' => 'success', 'info' => 'info'] as $key => $type)
                @if(session($key))
                    <div x-data x-init="$toast(@js(session($key)), { type: @js($type) })"></div>
                    <noscript><x-ui.alert :tone="$type">{{ session($key) }}</x-ui.alert></noscript>
                @endif
            @endforeach
            @if(session('error'))
                <x-ui.alert tone="error" dismissible>{{ session('error') }}</x-ui.alert>
            @endif
            @if(session('warning'))
                <x-ui.alert tone="warning" dismissible>{{ session('warning') }}</x-ui.alert>
            @endif
            {{-- Fehlerübersicht: Jeder Eintrag springt zum Feld (die Meldung am Feld
                 bleibt). Vorher stand dieselbe Meldung einfach zweimal da. Fehler des
                 Passwort-Dialogs zeigt der Dialog selbst. --}}
            @php
                $fehler = collect($errors->getMessages())->except(['current_password', 'password']);
            @endphp
            @if($fehler->isNotEmpty())
                <x-ui.alert tone="error">
                    <p class="font-semibold">
                        {{ $fehler->count() === 1 ? 'Bitte prüfe diese Angabe:' : 'Bitte prüfe diese ' . $fehler->count() . ' Angaben:' }}
                    </p>
                    <ul class="mt-1 list-disc list-inside space-y-1">
                        @foreach($fehler as $feld => $meldungen)
                            <li><span data-error-field="{{ $feld }}">{{ $meldungen[0] }}</span></li>
                        @endforeach
                    </ul>
                </x-ui.alert>
                <script>
                    // Eintraege mit passendem Feld auf der Seite werden zu Sprungmarken
                    document.addEventListener('DOMContentLoaded', () => {
                        document.querySelectorAll('[data-error-field]').forEach((el) => {
                            const key = el.dataset.errorField;
                            const name = key.split('.').map((p, i) => (i ? '[' + p + ']' : p)).join('');
                            const field = [name, name + '[]', key].map((n) => document.querySelector('[name="' + CSS.escape(n) + '"]')).find(Boolean);
                            if (!field || field.type === 'hidden') return;
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'text-left underline underline-offset-2 hover:no-underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 rounded';
                            btn.textContent = el.textContent;
                            btn.addEventListener('click', () => { field.scrollIntoView({ block: 'center' }); field.focus({ preventScroll: true }); });
                            el.replaceWith(btn);
                        });
                    });
                </script>
            @endif
        </div>

        <main id="main" tabindex="-1" class="flex-1 px-4 lg:px-6 focus:outline-none {{ $navBottom ? 'pb-24 lg:pb-8' : 'pb-8' }}">
            @yield('content')
        </main>

        <footer class="text-center text-xs text-gray-600 py-4 border-t border-gray-100 space-x-3 {{ $navBottom ? 'mb-16 lg:mb-0' : '' }}">
            <span>WaRa-Portal &copy; {{ date('Y') }} – SG Wasserratten Norderstedt e.V.</span>
            <a href="{{ route('legal.impressum') }}" class="hover:text-gray-900 underline">Impressum</a>
            <a href="{{ route('legal.datenschutz') }}" class="hover:text-gray-900 underline">Datenschutz</a>
        </footer>
    </div>

    {{-- Untere Navigation fuer Schwimmer und Eltern (mobil) --}}
    @if($navBottom)
        <nav aria-label="Schnellnavigation"
             class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-gray-200 shadow-[0_-2px_8px_rgba(0,0,0,0.05)]"
             style="padding-bottom: env(safe-area-inset-bottom)">
            <ul class="grid" style="grid-template-columns: repeat({{ count($navBottom) + 1 }}, minmax(0, 1fr))">
                @foreach($navBottom as $item)
                    <li>
                        <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif
                           class="flex flex-col items-center justify-center gap-0.5 min-h-[56px] text-xs font-medium {{ $item['active'] ? 'text-primary' : 'text-gray-600 hover:text-gray-900' }}">
                            <x-ui.icon :name="$item['icon']" class="w-6 h-6" />
                            <span class="truncate max-w-full px-1">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
                <li>
                    <button type="button" @click="sidebarOpen = true" aria-controls="sidebar" :aria-expanded="sidebarOpen ? 'true' : 'false'"
                            class="w-full flex flex-col items-center justify-center gap-0.5 min-h-[56px] text-xs font-medium text-gray-600 hover:text-gray-900">
                        <x-ui.icon name="menu" class="w-6 h-6" />
                        <span>Mehr</span>
                    </button>
                </li>
            </ul>
        </nav>
    @endif
</div>

{{-- Passwort aendern (fuer alle Rollen) --}}
<x-ui.dialog name="password" title="Passwort ändern" size="sm" guard
             :show="$errors->has('current_password') || $errors->has('password')">
    <form id="pw-form" method="POST" action="{{ route('password.update') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-4">
        @csrf
        @method('PUT')
        <x-ui.field label="Aktuelles Passwort" name="current_password" type="password" required autocomplete="current-password" data-autofocus />
        <x-ui.field label="Neues Passwort" name="password" type="password" required autocomplete="new-password"
                    hint="Mindestens 8 Zeichen, Buchstaben und Zahlen" />
        <x-ui.field label="Neues Passwort bestätigen" name="password_confirmation" type="password" required autocomplete="new-password" />
        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
            <x-ui.button variant="secondary" @click="close()">Abbrechen</x-ui.button>
            <x-ui.button type="submit" ::disabled="busy">
                <span x-show="!busy">Passwort speichern</span>
                <span x-show="busy" x-cloak>Wird gespeichert…</span>
            </x-ui.button>
        </div>
    </form>
</x-ui.dialog>

<x-ui.confirm-dialog />
<x-ui.toaster />

@stack('scripts')
</body>
</html>
