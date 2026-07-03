@extends('layouts.app')

@section('title', 'Warning #' . $warning->warning_number)
@section('activeNav', 'admin-users')
@section('admin')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <div class="page-header-row">
            <div>
                <h1>Warning #{{ $warning->warning_number }}</h1>
                <p>Issued to {{ $warning->user->full_name }}</p>
            </div>
            <a href="{{ route('admin.warnings.index') }}" class="back-link">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to warnings
            </a>
        </div>
    </header>

    <section class="card page-stack">
        <div class="profile-summary-list">
            <span><strong>User:</strong> {{ $warning->user->full_name }} ({{ $warning->user->email }})</span>
        </div>

        <div>
            <strong>Reason</strong>
            <p>{{ $warning->reason }}</p>
        </div>

        <div class="form-grid-2">
            <div>
                <strong>Issued by</strong>
                <p>{{ $warning->createdBy->full_name ?? 'System' }}</p>
            </div>
            <div>
                <strong>Issued at</strong>
                <p>{{ $warning->created_at->format('M d, Y H:i') }}</p>
            </div>
        </div>

        <div>
            <strong>Status</strong>
            <div class="profile-summary-list">
                @if ($warning->is_resolved)
                    <span class="badge badge-success">Resolved</span>
                    <span class="meta-text">Resolved at {{ $warning->resolved_at->format('M d, Y H:i') }}</span>
                @else
                    <span class="badge badge-warning">Pending</span>
                    <span class="meta-text">Deadline: {{ $warning->response_deadline->format('M d, Y H:i') }}</span>
                @endif
            </div>
        </div>

        @if (!$warning->is_resolved)
            <form method="POST" action="{{ route('admin.warnings.update', $warning) }}">
                @csrf
                @method('PUT')
                <button type="submit" class="btn btn-success">Resolve warning</button>
            </form>
        @endif
    </section>
</div>
@endsection
