@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
    <div class="page-header-row">
        <div class="page-header" style="margin-bottom: 0;">
            <h1>Change Password</h1>
            <p>Update your account password to keep it secure.</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="back-link">
            <span class="material-symbols-outlined" style="font-size: 1.125rem;">arrow_back</span>
            Back to Profile
        </a>
    </div>

    <div style="max-width: 600px;">
        <div class="bento-card profile-form-card">
            <form method="POST" action="{{ route('password.change.update') }}">
                @csrf

                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <div class="form-input-icon-wrap">
                        <span class="material-symbols-outlined">lock</span>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="form-control @error('current_password') is-invalid @enderror"
                            required
                            autocomplete="current-password"
                            autofocus
                        >
                    </div>
                    @error('current_password')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-section-divider" style="margin-top: 1.5rem;">
                    <h3 style="font-size: 1.125rem; margin-bottom: 0.25rem;">New Password</h3>
                    <p class="text-muted" style="font-size: 0.8125rem; margin-bottom: 0;">
                        Must be different from your current password.
                    </p>
                </div>

                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="form-input-icon-wrap">
                        <span class="material-symbols-outlined">key</span>
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            class="form-control @error('new_password') is-invalid @enderror"
                            required
                            autocomplete="new-password"
                        >
                    </div>
                    <small class="form-text">
                        Password requirements:
                        <ul style="margin-top: 0.5rem; padding-left: 1.25rem; margin-bottom: 0;">
                            <li>At least 8 characters</li>
                            <li>Mix of uppercase and lowercase letters</li>
                            <li>At least one number</li>
                        </ul>
                    </small>
                    @error('new_password')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                    <div class="form-input-icon-wrap">
                        <span class="material-symbols-outlined">key</span>
                        <input
                            type="password"
                            id="new_password_confirmation"
                            name="new_password_confirmation"
                            class="form-control @error('new_password_confirmation') is-invalid @enderror"
                            required
                            autocomplete="new-password"
                        >
                    </div>
                    @error('new_password_confirmation')
                    <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-actions-row">
                    <button type="submit" class="btn btn-primary btn-lg">Update Password</button>
                    <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
