@extends('layouts.guest')

@section('title', 'Login')

@section('content')
<div class="login-card">
    <div class="card-header-custom">
        <h2 class="card-title">Member Login</h2>
        <p class="card-subtitle">Enter your credentials to continue</p>
    </div>

    <form class="login-form" method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email" class="form-label">Email address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="e.g. john@example.com"
                class="form-control"
                required
                autocomplete="email"
            >
            @error('email')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <div class="password-wrapper">
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                >
                <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                        <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                    </svg>
                </button>
            </div>
            @error('password')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        @if ($errors->has('email') && str_contains(strtolower($errors->first('email')), 'suspend'))
            <div class="alert alert-warning" role="alert">
                {{ $errors->first('email') }}
            </div>
        @elseif ($errors->any())
            <div class="alert alert-error" role="alert">
                @if ($errors->has('email') || $errors->has('password'))
                    Invalid email or password.
                @else
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                @endif
            </div>
        @endif

        <div class="remember-group">
            <input
                type="checkbox"
                id="remember"
                name="remember"
                value="1"
                {{ old('remember') ? 'checked' : '' }}
            >
            <label for="remember">Remember me for 30 days</label>
        </div>

        <div class="submit-wrapper">
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </div>

        <p class="register-link">
            New member? <a href="{{ route('register') }}">Register here</a>
        </p>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const password = document.getElementById('password');
        const isHidden = password.type === 'password';
        password.type = isHidden ? 'text' : 'password';
        this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    });
</script>
@endpush
