@push('styles')
<style>
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    .detail-field {
        display: grid;
        gap: 4px;
    }
    .detail-field__label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--app-text-muted);
    }
    .detail-field__value {
        font-size: 14px;
        color: var(--app-text-primary);
        word-break: break-word;
    }
    .detail-field__value--mono {
        font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
        font-size: 13px;
    }
    .diff-box {
        border-radius: var(--radius-card);
        padding: 16px;
        overflow-x: auto;
        font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
        font-size: 12px;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .diff-box--old {
        background: color-mix(in srgb, var(--app-danger-soft) 40%, var(--app-card-bg));
        border: 1px solid var(--app-danger-soft);
        color: var(--app-danger);
    }
    .diff-box--new {
        background: color-mix(in srgb, var(--app-success-soft) 40%, var(--app-card-bg));
        border: 1px solid var(--app-success-soft);
        color: var(--app-success);
    }
    .card-stack {
        display: grid;
        gap: 16px;
    }
</style>
@endpush

@extends('layouts.app')

@section('title', 'Audit Log Details')

@section('content')
<div class="page-stack">
    {{-- Back link --}}
    <div>
        <a href="{{ route('admin.audit-logs.index') }}" class="back-link">
            <span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span>
            Back to audit logs
        </a>
    </div>

    <header class="page-header">
        <h1>Audit log details</h1>
        <p>Full details for log entry #{{ $log->id }}.</p>
    </header>

    <div class="card-stack">
        {{-- Card: Basic information --}}
        <div class="card">
            <h2 class="section-title" style="margin-bottom:16px;">Basic information</h2>
            <div class="detail-grid">
                <div class="detail-field">
                    <span class="detail-field__label">Log ID</span>
                    <span class="detail-field__value detail-field__value--mono">#{{ $log->id }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-field__label">Timestamp</span>
                    <span class="detail-field__value">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-field__label">User</span>
                    <span class="detail-field__value">{{ $log->user?->full_name ?? 'System' }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-field__label">IP address</span>
                    <span class="detail-field__value detail-field__value--mono">{{ $log->ip_address ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        {{-- Card: Action details --}}
        <div class="card">
            <h2 class="section-title" style="margin-bottom:16px;">Action details</h2>
            <div class="detail-grid">
                <div class="detail-field">
                    <span class="detail-field__label">Action</span>
                    <span class="detail-field__value">
                        <span class="badge badge-info">{{ $log->action_label }}</span>
                    </span>
                </div>
                <div class="detail-field">
                    <span class="detail-field__label">Action code</span>
                    <span class="detail-field__value detail-field__value--mono" style="font-size:12px;">{{ $log->action }}</span>
                </div>
                <div class="detail-field" style="grid-column:1/-1;">
                    <span class="detail-field__label">Description</span>
                    <span class="detail-field__value">{{ $log->formatted_description }}</span>
                </div>
            </div>
        </div>

        {{-- Card: Target information --}}
        @if ($log->target_type)
        <div class="card">
            <h2 class="section-title" style="margin-bottom:16px;">Target information</h2>
            <div class="detail-grid">
                <div class="detail-field">
                    <span class="detail-field__label">Target type</span>
                    <span class="detail-field__value detail-field__value--mono" style="font-size:12px;">{{ $log->target_type }}</span>
                </div>
                <div class="detail-field">
                    <span class="detail-field__label">Target ID</span>
                    <span class="detail-field__value detail-field__value--mono">#{{ $log->target_id }}</span>
                </div>
            </div>
        </div>
        @endif

        {{-- Card: Old values --}}
        @if ($log->old_values)
        <div class="card">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                <span class="badge badge-danger">Before</span>
                <h2 class="section-title">Old values</h2>
            </div>
            <div class="diff-box diff-box--old">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</div>
        </div>
        @endif

        {{-- Card: New values --}}
        @if ($log->new_values)
        <div class="card">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                <span class="badge badge-success">After</span>
                <h2 class="section-title">New values</h2>
            </div>
            <div class="diff-box diff-box--new">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</div>
        </div>
        @endif

        {{-- Card: User agent --}}
        @if ($log->user_agent)
        <div class="card">
            <h2 class="section-title" style="margin-bottom:16px;">User agent</h2>
            <div class="diff-box" style="background:color-mix(in srgb, var(--app-page-bg) 60%, var(--app-card-bg));border:1px solid var(--app-border);color:var(--app-text-secondary);">
                {{ $log->user_agent }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
