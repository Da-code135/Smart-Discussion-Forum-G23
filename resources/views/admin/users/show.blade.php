@extends('layouts.app')

@section('title', 'User: ' . $user->full_name)
@section('admin')

@section('content')
<div class="container" style="max-width: 900px;">

    {{-- Back link --}}
    <div style="margin-bottom: 1rem;">
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary" style="font-size: 0.875rem;">&larr; Back to Users</a>
    </div>

    {{-- User Profile Header --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin: 0;">{{ $user->full_name }}</h2>
                <span style="color: var(--text-muted); font-size: 0.875rem;">{{ $user->email }}</span>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <span class="status-badge status-{{ $user->account_status }}">
                    {{ ucfirst($user->account_status) }}
                </span>
                <span class="role-badge">{{ $user->role->role_name }}</span>
                @if ($user->id === auth()->id())
                    <span class="badge badge-info" style="font-size: 0.75rem;">You</span>
                @endif
            </div>
        </div>

        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <strong>Role</strong><br>
                    {{ $user->role->role_name }}
                </div>
                <div>
                    <strong>Group</strong><br>
                    {{ $user->group ? $user->group->group_name : '—' }}
                </div>
                <div>
                    <strong>Account Status</strong><br>
                    <span class="status-badge status-{{ $user->account_status }}">
                        {{ ucfirst($user->account_status) }}
                    </span>
                </div>
                <div>
                    <strong>Email Verified</strong><br>
                    @if ($user->email_verified_at)
                        <span class="badge badge-success">Yes</span>
                        <small style="color: var(--text-muted);">({{ $user->email_verified_at->format('M d, Y H:i') }})</small>
                    @else
                        <span class="badge" style="background: #dc3545; color: #fff;">No</span>
                    @endif
                </div>
                <div>
                    <strong>Last Active</strong><br>
                    {{ $user->last_active_at ? $user->last_active_at->format('M d, Y H:i') : 'Never' }}
                </div>
                <div>
                    <strong>Joined</strong><br>
                    {{ $user->created_at->format('M d, Y H:i') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Action Buttons (System Admin only for destructive actions) --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">Actions</div>
        <div class="card-body">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm">
                    <span class="material-symbols-outlined">edit</span>
                    Edit User
                </a>

                {{-- Lift Blacklist (if currently blacklisted) --}}
                @if ($user->account_status === 'blacklisted')
                    <form method="POST" action="{{ route('admin.users.lift-blacklist', $user) }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Lift blacklist for this user?')">
                            Lift Blacklist
                        </button>
                    </form>
                @endif

                {{-- Change Role (System Admin only, not for self) --}}
                @if (auth()->user()->isSystemAdmin() && $user->id !== auth()->id())
                    <form method="POST" action="{{ route('admin.users.change-role', $user) }}" style="display: inline-flex; gap: 0.25rem; align-items: center;">
                        @csrf
                        <select name="role_id" class="form-control" style="display: inline-block; width: auto; padding: 0.25rem 0.5rem; font-size: 0.875rem;">
                            @foreach (\App\Models\Role::all() as $r)
                                <option value="{{ $r->id }}" {{ $user->role_id == $r->id ? 'selected' : '' }}>
                                    {{ $r->role_name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Change role for this user?')">
                            Change Role
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Issue Warning Form --}}
    @can('create', \App\Models\Warning::class)
        @if(auth()->user()->canAdminUser($user))
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">Issue Warning</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.warnings.store') }}">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                        <div class="form-group">
                            <label for="reason" class="form-label">Warning Reason</label>
                            <textarea 
                                name="reason" 
                                id="reason" 
                                class="form-control @error('reason') is-invalid @enderror" 
                                rows="3" 
                                placeholder="Enter reason for warning..."
                                required
                            >{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to issue a warning to this user?')">
                            Issue Warning
                        </button>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    {{-- Warning History --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">Warning History ({{ $warnings->count() }})</div>
        <div class="card-body">
            @if ($warnings->isEmpty())
                <p style="color: var(--text-muted); text-align: center; padding: 1.5rem;">No warnings issued.</p>
            @else
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Reason</th>
                                <th>Deadline</th>
                                <th>Acknowledged</th>
                                <th>Resolved</th>
                                <th>Issued By</th>
                                <th>Issued At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($warnings as $warning)
                                <tr>
                                    <td>Warning {{ $warning->warning_number }}</td>
                                    <td>{{ $warning->reason ?? '—' }}</td>
                                    <td>
                                        @if ($warning->response_deadline)
                                            {{ $warning->response_deadline->format('M d, Y') }}
                                            @if ($warning->response_deadline->isPast() && !$warning->is_acknowledged)
                                                <span class="badge" style="background: #dc3545; color: #fff; font-size: 0.7rem;">Expired</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if ($warning->is_acknowledged)
                                            <span class="badge badge-success">Yes</span>
                                        @else
                                            <span class="badge" style="background: #ffc107; color: #000;">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($warning->is_resolved)
                                            <span class="badge badge-success">Yes</span>
                                            @if ($warning->resolved_at)
                                                <small style="color: var(--text-muted);">({{ $warning->resolved_at->format('M d, Y') }})</small>
                                            @endif
                                        @else
                                            <span class="badge" style="background: #6c757d; color: #fff;">No</span>
                                        @endif
                                    </td>
                                    <td>{{ $warning->createdBy ? $warning->createdBy->full_name : 'System' }}</td>
                                    <td>{{ $warning->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Blacklist History --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">Blacklist History ({{ $blacklistRecords->count() }})</div>
        <div class="card-body">
            @if ($blacklistRecords->isEmpty())
                <p style="color: var(--text-muted); text-align: center; padding: 1.5rem;">No blacklist records.</p>
            @else
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Reason</th>
                                <th>Blacklisted At</th>
                                <th>Expires At</th>
                                <th>Lifted At</th>
                                <th>Lifted By</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($blacklistRecords as $record)
                                <tr>
                                    <td>{{ $record->reason ?? '—' }}</td>
                                    <td>{{ $record->blacklisted_at ? $record->blacklisted_at->format('M d, Y H:i') : '—' }}</td>
                                    <td>
                                        @if ($record->expires_at)
                                            {{ $record->expires_at->format('M d, Y H:i') }}
                                        @else
                                            <span style="color: var(--text-muted);">Permanent</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($record->lifted_at)
                                            {{ $record->lifted_at->format('M d, Y H:i') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $record->liftedBy ? $record->liftedBy->full_name : '—' }}</td>
                                    <td>
                                        @if ($record->lifted_at)
                                            <span class="badge badge-success">Lifted</span>
                                        @else
                                            <span class="badge" style="background: #dc3545; color: #fff;">Active</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Onboarding Agreements --}}
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">Onboarding Agreements ({{ $onboardingAgreements->count() }})</div>
        <div class="card-body">
            @if ($onboardingAgreements->isEmpty())
                <p style="color: var(--text-muted); text-align: center; padding: 1.5rem;">No onboarding records.</p>
            @else
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Agreed</th>
                                <th>Version</th>
                                <th>IP Address</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($onboardingAgreements as $agreement)
                                <tr>
                                    <td>
                                        @if ($agreement->agreed)
                                            <span class="badge badge-success">Accepted</span>
                                        @else
                                            <span class="badge" style="background: #dc3545; color: #fff;">Declined</span>
                                        @endif
                                    </td>
                                    <td>{{ $agreement->agreement_version }}</td>
                                    <td>{{ $agreement->ip_address ?? '—' }}</td>
                                    <td>{{ $agreement->agreed_at ? \Carbon\Carbon::parse($agreement->agreed_at)->format('M d, Y H:i') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection