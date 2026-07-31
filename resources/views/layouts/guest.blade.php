<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <script>
            (function () {
                const theme = localStorage.getItem('theme') || 'system';
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && prefersDark));
            })();
        </script>

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">

        <title>{{ config('app.name', 'Mueble Desk') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased soft-gradient">
        <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-3 rounded-3xl bg-slate-950 px-5 py-4 text-white shadow-2xl shadow-indigo-500/10 dark:bg-white dark:text-slate-950">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 text-xl font-black dark:bg-slate-950/10">M</span>
                        <span class="text-left">
                            <span class="block text-sm font-extrabold leading-tight">{{ config('app.name', 'Mueble Desk') }}</span>
                            <span class="block text-xs text-white/70 dark:text-slate-600">Modern invoice workspace</span>
                        </span>
                    </a>
                </div>

                <div class="glass-panel p-6 sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </body>
</html>
