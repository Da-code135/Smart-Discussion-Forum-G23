<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta-description', 'Studdit academic discussion platform')">
    <title>@yield('title', 'Dashboard') - Studdit</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="app-layout">
    @include('components.navbar')

    <div class="app-shell">
        <main class="app-main">
            <div class="flash-stack">
                @if (session('success'))
                    <div class="alert alert-success" role="alert">
                        <span class="material-symbols-outlined">check_circle</span>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-error" role="alert">
                        <span class="material-symbols-outlined">error</span>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="alert alert-warning" role="alert">
                        <span class="material-symbols-outlined">warning</span>
                        <span>{{ session('warning') }}</span>
                    </div>
                @endif

                @if (session('info'))
                    <div class="alert alert-info" role="alert">
                        <span class="material-symbols-outlined">info</span>
                        <span>{{ session('info') }}</span>
                    </div>
                @endif
            </div>

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
