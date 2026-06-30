@extends('layouts.guest')

@section('title', 'Verify Email')

@section('content')
<div class="verify-card">
    <div class="verify-icon">📧</div>

    <h2>Verify Your Email</h2>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif

    @if (Auth::check())
        <p>We've sent a verification email to:</p>
        <p style="font-weight: 600; color: var(--forum-blue);">{{ Auth::user()->email }}</p>

        <p>Please click the link in the email to verify your address. The link will expire in 24 hours.</p>

        <div class="alert alert-info">
            💡 <strong>Didn't receive the email?</strong> Check your spam folder or request a new verification email below.
        </div>

        {{-- Resend Button --}}
        <form method="POST" action="{{ route('verify-email.resend') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                Resend Verification Email
            </button>
        </form>

        <div class="divider">or</div>

        {{-- Continue to Dashboard --}}
        <p>Already verified?</p>
        <a href="{{ route('dashboard') }}" class="btn btn-link">Go to Dashboard</a>
    @else
        <p>Please log in to verify your email address.</p>
        <a href="{{ route('login') }}" class="btn btn-primary">Go to Login</a>
    @endif
</div>
@endsection
