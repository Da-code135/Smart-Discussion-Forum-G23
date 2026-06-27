@extends('layouts.app')

@section('title', 'Edit IP Address')
@section('admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('admin.ip-whitelist.index') }}" class="text-blue-600 hover:text-blue-900">
            ← Back to IP Whitelist
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg p-6 max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit IP Address</h1>

        <form action="{{ route('admin.ip-whitelist.update', $ipWhitelist) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- IP Address -->
            <div>
                <label for="ip_address" class="block text-sm font-medium text-gray-700 mb-2">
                    IP Address <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       name="ip_address" 
                       id="ip_address" 
                       value="{{ old('ip_address', $ipWhitelist->ip_address) }}"
                       required
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('ip_address') border-red-500 @enderror">
                @error('ip_address')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                </label>
                <input type="text" 
                       name="description" 
                       id="description" 
                       value="{{ old('description', $ipWhitelist->description) }}"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror">
                @error('description')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Is Active -->
            <div class="flex items-center">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" 
                       name="is_active" 
                       id="is_active" 
                       value="1"
                       {{ old('is_active', $ipWhitelist->is_active) ? 'checked' : '' }}
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="is_active" class="ml-2 block text-sm text-gray-700">
                    Active (IP can access admin panel)
                </label>
            </div>

            <!-- Expiration Date -->
            <div>
                <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-2">
                    Expiration Date
                </label>
                <input type="datetime-local" 
                       name="expires_at" 
                       id="expires_at" 
                       value="{{ old('expires_at', $ipWhitelist->expires_at?->format('Y-m-d\TH:i')) }}"
                       class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('expires_at') border-red-500 @enderror">
                @error('expires_at')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-gray-500 text-xs mt-1">Leave empty for permanent access</p>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition font-medium">
                    Update IP Address
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
