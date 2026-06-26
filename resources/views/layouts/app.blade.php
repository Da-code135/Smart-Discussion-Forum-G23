<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta-description', 'Smart Discussion Forum Dashboard')">
    
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    
    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    {{-- Stylesheets --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/forms.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}">
    
    {{-- Admin-specific styles --}}
    @hasSection('admin')
        <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @endif
    
    @stack('styles')
</head>
<body class="app-layout">
    {{-- Navigation Bar --}}
    @include('components.navbar')

    {{-- Flash Messages --}}
    <div class="container">
        @if (session('success'))
            <div class="alert alert-success" role="alert">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning" role="alert">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('info'))
            <div class="alert alert-info" role="alert">
                {{ session('info') }}
            </div>
        @endif
    </div>

    {{-- Main Content --}}
    <main class="app-container">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
