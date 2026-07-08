@if(request()->is('api/*'))
    {{-- This would be handled by the API response, not rendered as a view --}}
@else
@extends('layouts.app')

@section('title', 'Messages - ' . ($conversation->name ?? 'Conversation'))
@section('activeNav', 'conversations')

@section('content')
<div class="page-shell">
    <main class="page-shell__content page-stack">
        <div class="conversation-messages-container">
            <h2>Messages in {{ $conversation->name ?? ($conversation->type === 'direct' ? 'Direct Message' : 'Group Conversation') }}</h2>
            
            <div id="messages-list" class="messages-list">
                @forelse($messages as $message)
                    <div class="message-item" data-message-id="{{ $message->id }}">
                        <div class="message-sender">
                            <strong>{{ $message->sender->full_name }}</strong>
                            <small class="message-time">{{ $message->created_at->format('M j, Y g:i A') }}</small>
                        </div>
                        <div class="message-body">
                            {{ $message->body }}
                        </div>
                    </div>
                @empty
                    <div class="no-messages">
                        <p>No messages in this conversation yet.</p>
                    </div>
                @endforelse
            </div>
            
            @if($messages->hasPages())
                <div class="pagination-section">
                    {{ $messages->links() }}
                </div>
            @endif
        </div>
    </main>
</div>
@endsection

@endif