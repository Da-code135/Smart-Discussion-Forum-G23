@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="container" style="max-width: 600px;">
    <div class="card">
        <div class="card-header">Edit Profile</div>

        <div class="card-body">
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="{{ old('full_name', Auth::user()->full_name) }}"
                        class="form-control"
                        required
                    >
                    @error('full_name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', Auth::user()->email) }}"
                        class="form-control"
                        required
                    >
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Changing your email requires re-verification.</small>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
            </form>

            <div class="nav-buttons mt-4">
                <a href="{{ route('dashboard') }}">Back to Dashboard</a>
            </div>
        </div>
    </div>
</div>
@endsection
