@extends('admin.layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Warnings</h1>
        <a href="{{ route('admin.warnings.create') }}" class="btn btn-primary">Issue Warning</a>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('admin.warnings.index') }}" class="mb-4">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="Search warnings..." value="{{ request('search') }}">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>

    <!-- Warnings Table -->
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Warning #</th>
                    <th>Reason</th>
                    <th>Issued By</th>
                    <th>Issued At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($warnings as $warning)
                    <tr>
                        <td>{{ $warning->user->full_name }}<br><small>{{ $warning->user->email }}</small></td>
                        <td>{{ $warning->warning_number }}</td>
                        <td>{{ Str::limit($warning->reason, 50) }}</td>
                        <td>{{ $warning->createdBy->full_name }}</td>
                        <td>{{ $warning->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            @if($warning->is_resolved)
                                <span class="badge bg-success">Resolved</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.warnings.show', $warning) }}" class="btn btn-sm btn-info">View</a>
                            @if(!$warning->is_resolved)
                                <form action="{{ route('admin.warnings.update', $warning) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-sm btn-success">Resolve</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $warnings->links() }}
    </div>
</div>
@endsection