@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
<div class="container" style="max-width: 600px;">
    <div class="card">
        <div class="card-header">Change Password</div>

        <div class="card-body">
            <form method="POST" action="{{ route('password.change.update') }}">
                @csrf

                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input 
                        id="current_password" 
                        type="password" 
                        class="form-control @error('current_password') is-invalid @enderror"
                        name="current_password" 
                        required 
                        autocomplete="current-password" 
                        autofocus
                    >
                    @error('current_password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <input 
                        id="new_password" 
                        type="password" 
                        class="form-control @error('new_password') is-invalid @enderror"
                        name="new_password" 
                        required 
                        autocomplete="new-password"
                    >
                    <small class="form-text">
                        Password requirements:
                        <ul class="mb-0" style="margin-top: 0.5rem; padding-left: 1.25rem;">
                            <li>At least 8 characters</li>
                            <li>Mix of uppercase and lowercase letters</li>
                            <li>At least one number</li>
                            <li>Must be different from current password</li>
                        </ul>
                    </small>
                    @error('new_password')
                        <span class="invalid-feedback d-block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                    <input 
                        id="new_password_confirmation" 
                        type="password" 
                        class="form-control @error('new_password_confirmation') is-invalid @enderror"
                        name="new_password_confirmation" 
                        required 
                        autocomplete="new-password"
                    >
                    @error('new_password_confirmation')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block">Update Password</button>
            </form>
        </div>
    </div>
</div>
@endsection
