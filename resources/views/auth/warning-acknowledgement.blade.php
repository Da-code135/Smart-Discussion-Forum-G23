@extends('layouts.guest')

@section('title', 'Account Warning')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header" style="display: flex; align-items: center; gap: 0.5rem;">
        <span style="font-size: 1.25rem;">⚠️</span>
        <span>Account Warning</span>
    </div>
    <div class="card-body">
        @if ($warning)
            <div style="margin-bottom: 1.5rem;">
                <p style="margin-bottom: 0.75rem;">
                    Your account has received a warning with the following reason:
                </p>

                <blockquote style="margin: 0 0 1.25rem 0; padding: 0.75rem 1rem; background: var(--bg-muted, #f8f9fa); border-left: 4px solid var(--warning, #ffc107); border-radius: 0.25rem; font-style: italic;">
                    {{ $warning->reason }}
                </blockquote>

                <p style="margin-bottom: 1rem;">
                    <strong>Please acknowledge this warning to continue using {{ config('app.name') }}.</strong>
                </p>

                <div style="padding: 1rem; background: var(--bg-muted, #f8f9fa); border-radius: 0.5rem;">
                    <h3 style="margin-bottom: 0.5rem; font-size: 0.95rem;">What this means:</h3>
                    <ul style="margin: 0; padding-left: 1.25rem; line-height: 1.7;">
                        <li>Your account remains active</li>
                        <li>You can continue using the platform</li>
                        <li>Review the platform rules and participate responsibly</li>
                    </ul>
                </div>
            </div>
        @else
            <p>No unacknowledged warnings found.</p>
        @endif

        <form method="POST" action="{{ route('warning-acknowledgement.acknowledge') }}">
            @csrf
            <input type="hidden" name="acknowledge" value="1">
            @error('acknowledge')
                <div class="alert alert-error" role="alert" style="margin-bottom: 1rem;">{{ $message }}</div>
            @enderror
            <button type="submit" class="btn btn-warning btn-block">
                I Understand and Acknowledge
            </button>
        </form>
    </div>
</div>
@endsection
