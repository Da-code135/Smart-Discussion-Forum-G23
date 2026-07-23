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
                    @php
                        $isMine = $message->sender_id === auth()->id();
                    @endphp
                    <div class="message-item {{ $isMine ? 'message-item--mine' : 'message-item--theirs' }}" data-message-id="{{ $message->id }}">
                        @if (!$isMine)
                            <span class="message-sender-name">{{ $message->sender->full_name }}</span>
                        @endif
                        <div class="message-bubble">
                            <div class="message-body">{{ $message->body }}</div>
                            <div class="message-footer">
                                <span class="message-time">{{ $message->created_at->format('g:i A') }}</span>
                            </div>
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