@extends('layouts.app')

@section('title', 'Conversations')
@section('activeNav', 'conversations')

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack">
        <header class="page-header">
            <div class="page-header-row">
                <div>
                    <h1>Conversations</h1>
                    <p>Direct messages and group chats with your group members.</p>
                </div>
                <a href="{{ route('conversations.create') }}" class="btn btn-primary">
                    <span class="material-symbols-outlined">add</span>
                    New conversation
                </a>
            </div>
        </header>

        <section class="topic-list">
            @forelse ($conversations as $conversation)
                @php
                    $isGroup = $conversation->type === 'group';
                    $otherParticipants = $conversation->participants->reject(
                        fn ($p) => $p->id === auth()->id()
                    );
                    $names = $isGroup
                        ? $conversation->name
                        : $otherParticipants->pluck('full_name')->implode(', ');
                    $lastMsg = $conversation->lastMessage;
                @endphp
                <a href="{{ route('conversations.show', $conversation->id) }}" class="discussion-item">
                    <div class="app-topbar-avatar" style="--avatar-bg: var(--avatar-tone-{{ ($conversation->id % 5) + 1 }});">
                        <span class="material-symbols-outlined" style="font-size: 20px;">
                            {{ $isGroup ? 'group' : 'person' }}
                        </span>
                    </div>
                    <div class="topic-row__body">
                        <div class="discussion-meta">
                            <span>{{ $names ?: 'Unknown' }}</span>
                            @if ($lastMsg)
                                <span class="discussion-meta-dot"></span>
                                <span>{{ $lastMsg->created_at->diffForHumans() }}</span>
                            @endif
                        </div>
                        <h3>
                            @if ($isGroup)
                                {{ $conversation->name ?: 'Group' }}
                            @else
                                {{ $names ?: 'Conversation' }}
                            @endif
                        </h3>
                        @if ($lastMsg)
                            <p class="topic-row__excerpt">{{ Str::limit($lastMsg->body, 100) }}</p>
                        @else
                            <p class="topic-row__excerpt" style="color: var(--text-muted);">No messages yet.</p>
                        @endif
                    </div>
                    <span class="section-link">Open <span class="material-symbols-outlined">arrow_forward</span></span>
                </a>
            @empty
                <div class="empty-state">
                    <span class="material-symbols-outlined" style="font-size: 40px;">chat</span>
                    <h2>No conversations yet</h2>
                    <p>Start a conversation with a group member to see it here.</p>
                    <a href="{{ route('conversations.create') }}" class="btn btn-primary">Start conversation</a>
                </div>
            @endforelse
        </section>

        @if ($conversations->hasPages())
            <section class="pagination-section">
                {{ $conversations->links() }}
            </section>
        @endif
    </div>

    <aside class="page-shell__sidebar page-stack">
        <section class="sidebar-card page-stack">
            <h2>About conversations</h2>
            <p>Conversations are private — only the people you invite can see what's written here.</p>
            <div class="profile-summary-list" style="margin-top: 8px;">
                <span class="badge badge-secondary">{{ $conversations->total() }} conversations</span>
            </div>
        </section>

        <section class="sidebar-card page-stack">
            <h2>Tips</h2>
            <ul style="padding-left: 16px;">
                <li>Use direct messages for 1-to-1 chats</li>
                <li>Create a group to discuss with multiple people</li>
                <li>Topics stay until you archive them</li>
            </ul>
        </section>
    </aside>
</div>
@endsection
