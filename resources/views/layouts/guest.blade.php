<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="description" content="@yield('meta-description', 'Studdit - a calm academic discussion platform')">
    <title>@yield('title', 'Welcome') - Studdit</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="guest-layout @yield('body-class')">
    <main class="guest-shell">
        <section class="guest-main @yield('main-class')">
            <div class="brand-mark">
                <div class="brand-mark__name">Studdit</div>
                <p class="brand-mark__tagline">A calm place for academic discussion</p>
            </div>

            <div class="flash-stack">
                @if (session('success'))
                    <div class="alert alert-success" role="alert">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-error" role="alert">{{ session('error') }}</div>
                @endif
                @if (session('warning'))
                    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
                @endif
                @if (session('info'))
                    <div class="alert alert-info" role="alert">{{ session('info') }}</div>
                @endif
            </div>

            @yield('content')
        </section>
    </main>

    @stack('scripts')
</body>
</html>
