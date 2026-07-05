@extends('layouts.app')

@section('title', 'Blacklist Record')
@section('activeNav', 'admin-users')
@section('admin')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <div class="page-header-row">
            <div>
                <h1>Blacklist record</h1>
                <p>{{ $blacklistRecord->user->full_name }}</p>
            </div>
            <a href="{{ route('admin.blacklist.index') }}" class="back-link">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to blacklist
            </a>
        </div>
    </header>

    <section class="card page-stack">
        <div class="profile-summary-list">
            <span><strong>User:</strong> {{ $blacklistRecord->user->full_name }} ({{ $blacklistRecord->user->email }})</span>
        </div>

        <div>
            <strong>Blacklisted at</strong>
            <p>{{ $blacklistRecord->blacklisted_at->format('M d, Y H:i') }}</p>
        </div>

        <div>
            <strong>Reason</strong>
            <p>{{ $blacklistRecord->reason }}</p>
        </div>

        <div>
            <strong>Status</strong>
            <div class="profile-summary-list">
                @if ($blacklistRecord->lifted_at)
                    <span class="badge badge-success">Lifted</span>
                    <span class="meta-text">Lifted at {{ $blacklistRecord->lifted_at->format('M d, Y H:i') }} by {{ $blacklistRecord->liftedBy->full_name ?? 'Unknown' }}</span>
                @else
                    <span class="badge badge-danger">Active</span>
                @endif
            </div>
        </div>

        @if (!$blacklistRecord->lifted_at)
            <form method="POST" action="{{ route('admin.blacklist.update', $blacklistRecord) }}">
                @csrf
                @method('PUT')
                <button type="submit" class="btn btn-success">Lift blacklist</button>
            </form>
        @endif
    </section>
</div>
@endsection
