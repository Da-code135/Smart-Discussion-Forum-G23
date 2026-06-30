@extends('layouts.guest')

@section('title', 'Account Warning')

@section('content')
<div class="warning-container">
    <div class="warning-card">
        <div class="warning-icon">⚠️</div>

        <h2 class="warning-title">Account Warning</h2>

        <div class="warning-message">
            <p>Your account has received a warning due to inactivity or violation of platform rules.</p>

            <p><strong>Please acknowledge this warning to continue using {{ config('app.name') }}.</strong></p>

            <div class="warning-details">
                <h3>What this means:</h3>
                <ul>
                    <li>Your account remains active</li>
                    <li>You can continue using the platform</li>
                    <li>A second warning will result in automatic blacklisting</li>
                    <li>Review the platform rules and participate responsibly</li>
                </ul>
            </div>
        </div>

        <form method="POST" action="{{ route('warning-acknowledgement.acknowledge') }}">
            @csrf
            <button type="submit" class="btn btn-warning btn-block">
                I Understand and Acknowledge
            </button>
        </form>
    </div>
</div>
@endsection
