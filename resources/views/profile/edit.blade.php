@extends('layouts.app')

@section('title', 'Edit Profile')
@section('activeNav', 'profile')

@section('content')
@php
    $user = Auth::user();
    $initials = collect(explode(' ', $user->full_name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
    $memberSince = $user->created_at ? $user->created_at->format('F Y') : 'Recently';
    $avatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][$user->id % 5];
@endphp

<div class="page-shell">
    <div class="page-shell__main page-stack">
        <header class="page-header">
            <div class="page-header-row">
                <div>
                    <h1>Edit profile</h1>
                    <p>Update your personal information and account email.</p>
                </div>
                <a href="{{ route('dashboard') }}" class="back-link">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Back to dashboard
                </a>
            </div>
        </header>

        <section class="profile-form-card">
            <form method="POST" action="{{ route('profile.update') }}" class="form-stack">
                @csrf
                @method('PUT')

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full name</label>
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
                            <input type="text" id="role_display" value="{{ $user->role->role_name }}" class="form-control" readonly tabindex="-1" aria-readonly="true">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email address</label>
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
                        <span>Changing your email requires re-verification.</span>
                    </div>
                </div>

                <div class="form-section-divider">
                    <div>
                        <h2>Security</h2>
                        <p>Manage your password in a separate secure flow.</p>
                    </div>
                    <a href="{{ route('password.change') }}" class="btn btn-secondary">Change password</a>
                </div>

                <div class="form-actions-row">
                    <button type="submit" class="btn btn-primary btn-lg">Save changes</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-lg">Discard</a>
                </div>
            </form>
        </section>
    </div>

    <aside class="page-shell__sidebar page-stack">
        <section class="profile-sidebar-card page-stack">
            <a href="{{ route('profile.picture') }}" class="profile-avatar-wrap" aria-label="Change profile picture" style="--avatar-bg: {{ $avatarTone }};">
                @if ($user->profile_picture)
                    <img src="{{ asset('storage/' . $user->profile_picture) }}" alt="Profile picture of {{ $user->full_name }}" class="profile-avatar">
                @else
                    <div class="profile-avatar-placeholder">{{ $initials }}</div>
                @endif
                <div class="profile-avatar-overlay">
                    <span class="material-symbols-outlined">photo_camera</span>
                </div>
            </a>

            <div>
                <h2>{{ $user->full_name }}</h2>
                <p>{{ $user->role->role_name }}</p>
            </div>

            <div class="profile-summary-list">
                <span class="badge badge-secondary">{{ $user->group->group_name ?? 'General' }}</span>
                <span class="status-badge status-{{ $user->account_status }}">{{ ucfirst($user->account_status) }}</span>
            </div>

            <p class="meta-text">Member since {{ $memberSince }}</p>
            <a href="{{ route('profile.picture') }}" class="btn btn-secondary btn-block">Change picture</a>
        </section>

        <section class="privacy-notice">
            <span class="material-symbols-outlined">report</span>
            <div>
                <h5>Privacy notice</h5>
                <p>Some profile details are visible to other verified members to support academic integrity and accountability.</p>
            </div>
        </section>
    </aside>
</div>
@endsection
