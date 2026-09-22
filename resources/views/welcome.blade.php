<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'SentinelPay') }} — High-Availability Payment Platform</title>

        <!-- Fonts: Plus Jakarta Sans (UI) & JetBrains Mono (Technical/Monospace) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />

        <!-- Scripts and Styles -->
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="bg-[#020617] text-[#F8FAFC] min-h-screen antialiased selection:bg-blue-600/30 selection:text-white font-sans">
        <div id="root">
            <noscript>
                <div class="p-8 text-center max-w-md mx-auto mt-20 rounded-xl border border-slate-800 bg-[#0F172A] shadow-2xl">
                    <h1 class="text-xl font-bold text-slate-100">SentinelPay 2.0</h1>
                    <p class="text-sm text-slate-400 mt-2">
                        JavaScript is required to run the SentinelPay financial console.
                    </p>
                </div>
            </noscript>
        </div>
    </body>
</html>
