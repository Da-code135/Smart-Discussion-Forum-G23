@extends('layouts.guest')

@section('title', 'Register')

@section('content')
<div class="login-card">
    <div class="card-header-custom">
        <h2 class="card-title">Create Account</h2>
        <p class="card-subtitle">Join the Smart Discussion Forum</p>
    </div>

    <form class="login-form" method="POST" action="{{ route('register.store') }}">
        @csrf

        <div class="form-group">
            <label for="full_name" class="form-label">Full Name</label>
            <input 
                id="full_name" 
                type="text" 
                class="form-control @error('full_name') is-invalid @enderror"
                name="full_name" 
                value="{{ old('full_name') }}" 
                required 
                autocomplete="name" 
                autofocus
            >
            @error('full_name')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

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
            >
            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Password</label>
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
            <label for="password_confirmation" class="form-label">Confirm Password</label>
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
            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </div>

        <p class="register-link">
            Already have an account? <a href="{{ route('login') }}">Login here</a>
        </p>
    </form>
</div>
@endsection
