@extends('layouts.app')

@section('title', 'IP Whitelist')
@section('admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">IP Whitelist Management</h1>
            <p class="text-gray-600 mt-1">Control which IP addresses can access the admin panel</p>
        </div>
        <a href="{{ route('admin.ip-whitelist.create') }}" 
           class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition">
            + Add IP Address
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            {{ session('error') }}
        </div>
    @endif

    <!-- Info Box -->
    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <span class="text-blue-500 text-xl">ℹ️</span>
            </div>
            <div class="ml-3">
                <p class="text-sm text-blue-700">
                    <strong>IP Whitelisting</strong> restricts admin panel access to specific IP addresses. 
                    Only IPs in this list can access admin routes when whitelisting is enabled.
                </p>
            </div>
        </div>
    </div>

    <!-- IP Whitelist Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires At</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($ips as $ip)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                            {{ $ip->ip_address }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $ip->description ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $ip->status_color }}-100 text-{{ $ip->status_color }}-800">
                                {{ $ip->status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $ip->expires_at ? $ip->expires_at->format('Y-m-d H:i') : 'Never' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $ip->createdBy?->full_name ?? 'System' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            @if($ip->is_active)
                                <form action="{{ route('admin.ip-whitelist.deactivate', $ip) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900">
                                        Deactivate
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('admin.ip-whitelist.activate', $ip) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:text-green-900">
                                        Activate
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('admin.ip-whitelist.edit', $ip) }}" 
                               class="text-blue-600 hover:text-blue-900">
                                Edit
                            </a>
                            <form action="{{ route('admin.ip-whitelist.destroy', $ip) }}" method="POST" class="inline" 
                                  onsubmit="return confirm('Are you sure you want to remove this IP from the whitelist?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            No IP addresses in whitelist. Click "Add IP Address" to get started.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($ips->hasPages())
        <div class="mt-6">
            {{ $ips->links() }}
        </div>
    @endif
</div>
@endsection
