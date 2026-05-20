<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Anmelden') – WaRa-Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#1B5EAB', dark: '#0D3F7A' },
                        accent:  { DEFAULT: '#C0392B' }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-primary-dark via-primary to-blue-500 flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        {{-- Logo & Vereinsname --}}
        <div class="text-center mb-8">
            <img src="https://www.wasserratten.de/images/logo120x120.png"
                 alt="SG Wasserratten Norderstedt"
                 class="w-24 h-24 mx-auto rounded-full shadow-lg bg-white p-1 mb-4">
            <h1 class="text-white text-2xl font-bold">WaRa-Portal</h1>
            <p class="text-blue-200 text-sm mt-1">SG Wasserratten Norderstedt e.V.</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            @yield('content')
        </div>

        <p class="text-center text-blue-200 text-xs mt-6">
            &copy; {{ date('Y') }} SG Wasserratten Norderstedt e.V.
        </p>
    </div>
</body>
</html>
