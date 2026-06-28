@extends('layouts.app')

@section('title', 'Add IP Address')
@section('admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.ip-whitelist.index') }}" class="text-blue-600 hover:text-blue-900">
            ← Back to IP Whitelist
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-6 max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Add IP Address to Whitelist</h1>

        <form action="{{ route('admin.ip-whitelist.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- IP Address -->
            <div>
                <label for="ip_address" class="block text-sm font-medium text-gray-700 mb-2">
                    IP Address <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       name="ip_address" 
                       id="ip_address" 
                       value="{{ old('ip_address') }}"
                       placeholder="192.168.1.1"
                       required
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('ip_address') border-red-500 @enderror">
                @error('ip_address')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-gray-500 text-xs mt-1">Enter the IP address to whitelist (IPv4 or IPv6)</p>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                </label>
                <input type="text" 
                       name="description" 
                       id="description" 
                       value="{{ old('description') }}"
                       placeholder="Office IP, Admin Home, etc."
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror">
                @error('description')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-gray-500 text-xs mt-1">Optional description to identify this IP</p>
            </div>

            <!-- Expiration Date -->
            <div>
                <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-2">
                    Expiration Date
                </label>
                <input type="datetime-local" 
                       name="expires_at" 
                       id="expires_at" 
                       value="{{ old('expires_at') }}"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('expires_at') border-red-500 @enderror">
                @error('expires_at')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-gray-500 text-xs mt-1">Leave empty for permanent access</p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition font-medium">
                    Add IP Address
                </button>
                <a href="{{ route('admin.ip-whitelist.index') }}" 
                   class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg transition font-medium text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
