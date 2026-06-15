<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartung – SG Wasserratten Norderstedt</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#1B5EAB] min-h-screen flex items-center justify-center p-4">

<div class="max-w-md w-full text-center">
    <img src="https://www.wasserratten.de/images/logo96x96.png"
         alt="Logo" class="w-20 h-20 rounded-full bg-white/90 p-1 mx-auto mb-6 shadow-lg">

    <h1 class="text-2xl font-bold text-white mb-2">Wartungsarbeiten</h1>
    <p class="text-blue-100 text-sm leading-relaxed mb-8">
        {{ $message ?? 'Das Portal wird gerade gewartet. Bitte versuche es später erneut.' }}
    </p>

    <div class="bg-white/10 rounded-xl px-6 py-4 text-blue-100 text-xs">
        SG Wasserratten Norderstedt e.V. &mdash; WaRa-Portal
    </div>

    @auth
    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="text-blue-200 hover:text-white text-xs underline transition-colors">
            Abmelden
        </button>
    </form>
    @else
    <a href="{{ route('login') }}" class="mt-6 inline-block text-blue-200 hover:text-white text-xs underline transition-colors">
        Anmelden
    </a>
    @endauth
</div>

</body>
</html>
