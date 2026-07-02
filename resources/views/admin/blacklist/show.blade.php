@extends('admin.layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Blacklist Record</h1>
        <a href="{{ route('admin.blacklist.index') }}" class="btn btn-secondary">Back to Blacklist</a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    Blacklist Details
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>User:</strong>
                        <p>{{ $blacklistRecord->user->full_name }} ({{ $blacklistRecord->user->email }})</p>
                    </div>

                    <div class="mb-3">
                        <strong>Blacklisted At:</strong>
                        <p>{{ $blacklistRecord->blacklisted_at->format('Y-m-d H:i') }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Reason:</strong>
                        <p>{{ $blacklistRecord->reason }}</p>
                    </div>

                    <div class="mb-3">
                        <strong>Status:</strong>
                        @if($blacklistRecord->lifted_at)
                            <span class="badge bg-success">Lifted</span>
                            <p>Lifted at: {{ $blacklistRecord->lifted_at->format('Y-m-d H:i') }}</p>
                            <p>Lifted by: {{ $blacklistRecord->liftedBy->full_name }}</p>
                        @else
                            <span class="badge bg-danger">Active</span>
                        @endif
                    </div>

                    @if(!$blacklistRecord->lifted_at)
                        <form action="{{ route('admin.blacklist.update', $blacklistRecord) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="btn btn-success">Lift Blacklist</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection