<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">

    @auth
        <script>window.location = "{{ url('/dashboard') }}";</script>
    @else
        <div class="relative sm:flex sm:justify-center sm:items-center min-h-screen 
                    bg-gray-100 dark:bg-gray-900 selection:bg-red-500 selection:text-white">
            <div class="sm:fixed sm:top-0 sm:right-0 p-6 text-right z-10">
                <a href="{{ route('login') }}" 
                   class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                    Log in
                </a>

                @if (Route::has('register'))
                    <a href="{{ route('register') }}" 
                       class="ml-4 font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                        Register
                    </a>
                @endif
            </div>

            <div class="max-w-7xl mx-auto p-6 lg:p-8">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Welcome</h1>
                <p class="mt-4 text-gray-600 dark:text-gray-400">Please log in or register to continue.</p>
            </div>
        </div>
    @endauth

</body>
</html>
