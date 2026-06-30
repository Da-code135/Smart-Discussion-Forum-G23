@extends('layouts.app')

@section('title', 'Edit Profile')
@section('activeNav', 'profile')

@section('content')
@php
    $user = Auth::user();
    $initials = collect(explode(' ', $user->full_name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
    $memberSince = $user->created_at ? $user->created_at->format('F Y') : 'Recently';
@endphp

<div class="page-header-row">
    <div class="page-header" style="margin-bottom: 0;">
        <h1>Edit Profile</h1>
        <p>Update your personal information and credentials.</p>
    </div>
    <a href="{{ route('dashboard') }}" class="back-link">
        <span class="material-symbols-outlined" style="font-size: 1.125rem;">arrow_back</span>
        Back to Dashboard
    </a>
</div>

<div class="profile-grid">
    {{-- Left Column: Avatar & Status --}}
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="bento-card profile-sidebar-card">
            <a href="{{ route('profile.picture') }}" class="profile-avatar-wrap" aria-label="Change profile picture">
                @if ($user->profile_picture)
                    <img
                        src="{{ asset('storage/' . $user->profile_picture) }}"
                        alt="Profile picture of {{ $user->full_name }}"
                        class="profile-avatar"
                    >
                @else
                    <div class="profile-avatar-placeholder">{{ $initials }}</div>
                @endif
                <div class="profile-avatar-overlay">
                    <span class="material-symbols-outlined" style="color: var(--on-primary);">photo_camera</span>
                </div>
            </a>

            <h3 style="font-size: 1.25rem; margin-bottom: 0.25rem;">{{ $user->full_name }}</h3>
            <p class="text-muted" style="font-size: 0.875rem; margin-bottom: 1.5rem;">{{ $user->role->role_name }}</p>

            <a href="{{ route('profile.picture') }}" class="btn btn-tertiary btn-block">
                <span class="material-symbols-outlined" style="font-size: 1.125rem;">edit</span>
                Change Picture
            </a>
        </div>

        <div class="account-status-card">
            <h4 style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Account Status</h4>
            <div class="verified-badge">
                <span class="material-symbols-outlined filled" style="font-size: 1rem;">verified</span>
                <span>{{ ucfirst($user->account_status) }} Member</span>
            </div>
            <p style="font-size: 0.75rem; color: var(--on-surface-variant); margin-top: 0.5rem; margin-bottom: 0; line-height: 1.5;">
                Member since {{ $memberSince }}. Role: {{ $user->role->role_name }} in the Smart Discussion Forum.
            </p>
        </div>
    </div>

    {{-- Right Column: Form --}}
    <div>
        <div class="bento-card profile-form-card">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name</label>
                        <div class="form-input-icon-wrap">
                            <span class="material-symbols-outlined">person</span>
                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="{{ old('full_name', $user->full_name) }}"
                                class="form-control @error('full_name') is-invalid @enderror"
                                required
                                autocomplete="name"
                            >
                        </div>
                        @error('full_name')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="role_display" class="form-label">Role</label>
                        <div class="form-input-icon-wrap">
                            <span class="material-symbols-outlined">school</span>
                            <input
                                type="text"
                                id="role_display"
                                value="{{ $user->role->role_name }}"
                                class="form-control"
                                readonly
                                tabindex="-1"
                                aria-readonly="true"
                            >
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="form-input-icon-wrap">
                        <span class="material-symbols-outlined">mail</span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $user->email) }}"
                            class="form-control @error('email') is-invalid @enderror"
                            required
                            autocomplete="email"
                        >
                    </div>
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <div class="form-hint">
                        <span class="material-symbols-outlined">info</span>
                        <span>Changing your email requires re-verification. You will receive a secure link to confirm the update.</span>
                    </div>
                </div>

                <div class="form-section-divider">
                    <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">Security</h3>
                    <a href="{{ route('password.change') }}" class="back-link">
                        <span class="material-symbols-outlined" style="font-size: 1.125rem;">lock_reset</span>
                        Change Password
                    </a>
                </div>

                <div class="form-actions-row">
                    <button type="submit" class="btn btn-primary btn-lg">Save Changes</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-lg">Discard</a>
                </div>
            </form>
        </div>

        <div class="privacy-notice">
            <span class="material-symbols-outlined">report</span>
            <div>
                <h5 style="font-size: 0.875rem; font-weight: 600; color: var(--on-error-container); margin-bottom: 0.25rem;">Privacy Notice</h5>
                <p style="font-size: 0.875rem; color: var(--on-error-container); margin: 0; line-height: 1.5;">
                    Some profile details are visible to other verified members to maintain academic integrity and accountability within the Smart Discussion Forum.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
