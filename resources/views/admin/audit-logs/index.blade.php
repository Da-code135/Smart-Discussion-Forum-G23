@push('styles')
<style>
    .audit-row { transition: background-color 0.15s ease; }
    .audit-row:hover td { background: color-mix(in srgb, var(--app-accent-soft) 30%, var(--app-card-bg)); }
    .audit-description { max-width: 320px; }
    .export-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
</style>
@endpush

@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
<div class="page-stack">
    {{-- Header --}}
    <div class="admin-header">
        <div class="admin-header__row">
            <div>
                <h1>Audit logs</h1>
                <p>Track all administrative actions and system changes.</p>
            </div>
            <div class="export-bar">
                <a href="{{ route('admin.audit-logs.export', ['format' => 'csv'] + request()->query()) }}"
                   class="btn btn-sm btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:14px;">download</span>
                    CSV
                </a>
                <a href="{{ route('admin.audit-logs.export', ['format' => 'json'] + request()->query()) }}"
                   class="btn btn-sm btn-secondary">
                    <span class="material-symbols-outlined" style="font-size:14px;">download</span>
                    JSON
                </a>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="admin-header">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="form-stack">
            <div class="filter-section">
                <div class="form-group">
                    <label for="action" class="form-label">Action type</label>
                    <select name="action" id="action" class="form-control">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action['value'] }}" {{ ($filters['action'] ?? '') === $action['value'] ? 'selected' : '' }}>
                                {{ $action['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_date" class="form-label">Start date</label>
                    <input type="date" name="start_date" id="start_date"
                           value="{{ $filters['start_date'] ?? '' }}" class="form-control">
                </div>

                <div class="form-group">
                    <label for="end_date" class="form-label">End date</label>
                    <input type="date" name="end_date" id="end_date"
                           value="{{ $filters['end_date'] ?? '' }}" class="form-control">
                </div>

                <div class="filter-button-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="audit-row">
                        <td style="white-space:nowrap;">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->user?->full_name ?? 'System' }}</td>
                        <td>
                            <span class="badge badge-info">{{ $log->action_label }}</span>
                        </td>
                        <td class="audit-description" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $log->formatted_description }}
                        </td>
                        <td style="font-size:12px;color:var(--app-text-muted);font-family:monospace;">
                            {{ $log->ip_address ?? '—' }}
                        </td>
                        <td>
                            <a href="{{ route('admin.audit-logs.show', $log) }}" class="btn btn-sm btn-ghost">
                                Details
                                <span class="material-symbols-outlined" style="font-size:14px;">chevron_right</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;color:var(--app-text-muted);padding:2rem;">
                            No audit logs found matching your criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($logs->hasPages())
        <div class="pagination">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
