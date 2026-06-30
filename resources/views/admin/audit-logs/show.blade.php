@extends('layouts.app')

@section('title', 'Audit Log Details')
@section('admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.audit-logs.index') }}" class="text-blue-600 hover:text-blue-900">
            ← Back to Audit Logs
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Audit Log Details</h1>

        <div class="space-y-6">
            <!-- Basic Information -->
            <div class="border-b pb-4">
                <h2 class="text-xl font-semibold text-gray-700 mb-3">Basic Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="text-sm font-medium text-gray-500">Log ID:</span>
                        <p class="text-gray-900">{{ $log->id }}</p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Timestamp:</span>
                        <p class="text-gray-900">{{ $log->created_at->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">User:</span>
                        <p class="text-gray-900">{{ $log->user?->full_name ?? 'System' }}</p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">IP Address:</span>
                        <p class="text-gray-900">{{ $log->ip_address ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <!-- Action Details -->
            <div class="border-b pb-4">
                <h2 class="text-xl font-semibold text-gray-700 mb-3">Action Details</h2>
                <div class="space-y-3">
                    <div>
                        <span class="text-sm font-medium text-gray-500">Action:</span>
                        <p class="text-gray-900">
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $log->action_label }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Action Code:</span>
                        <p class="text-gray-900 font-mono text-sm">{{ $log->action }}</p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Description:</span>
                        <p class="text-gray-900">{{ $log->formatted_description }}</p>
                    </div>
                </div>
            </div>

            <!-- Target Information -->
            @if($log->target_type)
                <div class="border-b pb-4">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Target Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="text-sm font-medium text-gray-500">Target Type:</span>
                            <p class="text-gray-900 font-mono text-sm">{{ $log->target_type }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Target ID:</span>
                            <p class="text-gray-900">{{ $log->target_id }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Old Values -->
            @if($log->old_values)
                <div class="border-b pb-4">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">Old Values</h2>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <pre class="text-sm text-gray-800 overflow-x-auto">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            @endif

            <!-- New Values -->
            @if($log->new_values)
                <div class="border-b pb-4">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">New Values</h2>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <pre class="text-sm text-gray-800 overflow-x-auto">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            @endif

            <!-- User Agent -->
            @if($log->user_agent)
                <div>
                    <h2 class="text-xl font-semibold text-gray-700 mb-3">User Agent</h2>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <p class="text-sm text-gray-800 font-mono break-all">{{ $log->user_agent }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
