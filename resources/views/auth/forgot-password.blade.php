@extends('layouts.guest')

@section('title', 'Forgot Password')

@section('content')
<div class="login-card">
    <div class="card-header-custom">
        <h2 class="card-title">Forgot Password</h2>
        <p class="card-subtitle">Reset your account password</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <p class="text-muted mb-4">
        Enter your email address and we will send you a link to reset your password.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-group">
            <label for="email" class="form-label">Email Address</label>
            <input 
                id="email" 
                type="email" 
                class="form-control @error('email') is-invalid @enderror"
                name="email" 
                value="{{ old('email') }}" 
                required 
                autocomplete="email" 
                autofocus
            >
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="submit-wrapper">
            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
        </div>

        <p class="register-link">
            <a href="{{ route('login') }}">Back to Login</a>
        </p>
    </form>
</div>
@endsection
