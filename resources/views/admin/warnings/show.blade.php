@extends('admin.layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Warning #{{ $warning->warning_number }}</h1>
        <a href="{{ route('admin.warnings.index') }}" class="btn btn-secondary">Back to Warnings</a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    Warning Details
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>User:</strong>
                        <p>{{ $warning->user->full_name }} ({{ $warning->user->email }})</p>
                    </div>

                    <div class="mb-3">
                        <strong>Reason:</strong>
                        <p>{{ $warning->reason }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Issued By:</strong>
                        <p>{{ $warning->createdBy->full_name }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Issued At:</strong>
                        <p>{{ $warning->created_at->format('Y-m-d H:i') }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Response Deadline:</strong>
                        <p>{{ $warning->response_deadline->format('Y-m-d H:i') }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Status:</strong>
                        @if($warning->is_resolved)
                            <span class="badge bg-success">Resolved</span>
                            <p>Resolved at: {{ $warning->resolved_at->format('Y-m-d H:i') }}</p>
                        @else
                            <span class="badge bg-warning text-dark">Pending</span>
                            <p>Deadline: {{ $warning->response_deadline->format('Y-m-d H:i') }}</p>
                        @endif
                    </div>

                    @if(!$warning->is_resolved)
                        <form action="{{ route('admin.warnings.update', $warning) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn btn-success">Resolve Warning</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection