@extends('layouts.guest')

@section('title', 'Reset Password')

@section('content')
<div class="login-card">
    <div class="card-header-custom">
        <h2 class="card-title">Reset Password</h2>
        <p class="card-subtitle">Create a new password for your account</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label for="email" class="form-label">Email Address</label>
            <input 
                id="email" 
                type="email" 
                class="form-control" 
                name="email" 
                value="{{ $email }}" 
                readonly
            >
        </div>

        <div class="form-group">
            <label for="password" class="form-label">New Password</label>
            <input 
                id="password" 
                type="password" 
                class="form-control @error('password') is-invalid @enderror"
                name="password" 
                required 
                autocomplete="new-password"
            >
            <small class="form-text">
                Password requirements:
                <ul class="mb-0" style="margin-top: 0.5rem; padding-left: 1.25rem;">
                    <li>At least 8 characters</li>
                    <li>Mix of uppercase and lowercase letters</li>
                    <li>At least one number</li>
                </ul>
            </small>
            @error('password')
                <span class="invalid-feedback d-block" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation" class="form-label">Confirm New Password</label>
            <input 
                id="password_confirmation" 
                type="password" 
                class="form-control @error('password_confirmation') is-invalid @enderror"
                name="password_confirmation" 
                required 
                autocomplete="new-password"
            >
            @error('password_confirmation')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="submit-wrapper">
            <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
        </div>

        <p class="register-link">
            <a href="{{ route('login') }}">Back to Login</a>
        </p>
    </form>
</div>
@endsection
