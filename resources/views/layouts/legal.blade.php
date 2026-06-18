<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') – SG Wasserratten Norderstedt</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { primary: { DEFAULT: '#1B5EAB' } } } }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <header class="bg-[#1B5EAB] text-white px-6 py-4 flex items-center gap-4">
        <img src="https://www.wasserratten.de/images/logo96x96.png" alt="Logo" class="w-9 h-9 rounded-full bg-white/90 p-0.5">
        <div>
            <p class="font-bold text-sm leading-none">SG Wasserratten Norderstedt e.V.</p>
            <p class="text-xs text-blue-200">WaRa-Portal</p>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-10">
        @yield('content')
    </main>

    <footer class="border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-400 space-x-4">
        <a href="{{ route('legal.impressum') }}" class="hover:text-gray-600">Impressum</a>
        <a href="{{ route('legal.datenschutz') }}" class="hover:text-gray-600">Datenschutz</a>
        @auth
            <a href="{{ url()->previous() }}" class="hover:text-gray-600">← Zurück zum Portal</a>
        @endauth
        <span>&copy; {{ date('Y') }} SG Wasserratten Norderstedt e.V.</span>
    </footer>
</body>
</html>
