@extends('layouts.app')

@section('title', 'Profile Picture')

@section('content')
<div class="container" style="max-width: 400px;">
    <div class="card">
        <div class="card-header">Profile Picture</div>

        <div class="card-body text-center">
            @if (Auth::user()->profile_picture)
                <div class="current-picture">
                    <img
                        src="{{ asset('storage/' . Auth::user()->profile_picture) }}"
                        alt="Profile picture"
                        class="picture-preview"
                    >
                </div>
            @else
                <div class="current-picture">
                    <div class="placeholder">No picture yet</div>
                </div>
            @endif

            <form method="POST" action="{{ route('profile.picture.upload') }}" enctype="multipart/form-data" class="mt-4">
                @csrf

                <div class="form-group">
                    <input
                        type="file"
                        name="profile_picture"
                        accept="image/jpeg,image/png"
                        class="form-control"
                        required
                    >
                    @error('profile_picture')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">Upload Picture</button>

                <small class="form-text">
                    Supported formats: JPEG, PNG. Maximum size: 2MB
                </small>
            </form>

            <div class="nav-buttons">
                <a href="{{ route('profile.edit') }}">Back to Profile</a>
            </div>
        </div>
    </div>
</div>
@endsection
