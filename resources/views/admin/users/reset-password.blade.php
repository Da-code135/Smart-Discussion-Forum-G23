@extends('layouts.app')

@section('title', 'Reset Password: ' . $user->full_name)
@section('admin')

@section('content')
<div class="container" style="max-width: 500px;">

    {{-- Back link --}}
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary" style="font-size: 0.875rem;">&larr; Back to User</a>
    </div>

    <div class="card">
        <div class="card-header">Reset Password: {{ $user->full_name }}</div>

        <div class="card-body">
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                Set a new password for <strong>{{ $user->full_name }}</strong> ({{ $user->email }}).
                The user will need to use this new password on their next login.
            </p>

            <form method="POST" action="{{ route('admin.users.reset-password.store', $user) }}">
                @csrf

                <div class="form-group">
                    <label for="password" class="form-label">New Password *</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        required
                        minlength="8"
                        autofocus
                    >
                    @error('password')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Minimum 8 characters, with mixed case and at least one number</small>
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm New Password *</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="form-control"
                        required
                        minlength="8"
                    >
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
