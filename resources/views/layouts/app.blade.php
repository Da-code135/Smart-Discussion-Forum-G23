<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta-description', 'Smart Discussion Forum Dashboard')">

    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/forms.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}">

    @hasSection('admin')
        <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @endif

    @stack('styles')
</head>
<body class="app-layout">
    @include('components.navbar')

    <div class="app-shell">
        <main class="app-main">
            @if (session('success'))
                <div class="alert alert-success flash-alert" role="alert">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-error flash-alert" role="alert">
                    <span class="material-symbols-outlined">error</span>
                    {{ session('error') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning flash-alert" role="alert">
                    <span class="material-symbols-outlined">warning</span>
                    {{ session('warning') }}
                </div>
            @endif

            @if (session('info'))
                <div class="alert alert-info flash-alert" role="alert">
                    <span class="material-symbols-outlined">info</span>
                    {{ session('info') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>

you didn't explain " <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">" so this "@hasSection('admin')
        <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @endif" is an if block? what does "@stack('styles')" do. I also don't understand "<main class="app-main">
            @if (session('success'))
                <div class="alert alert-success flash-alert" role="alert">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif"......or will going through the login.blade.php cover these