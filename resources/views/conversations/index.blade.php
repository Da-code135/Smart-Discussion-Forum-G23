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
                <article class="post-card">
                    <a href="{{ route('conversations.show', $conversation->id) }}" class="post-card__content" style="text-decoration: none;">
                        <div class="post-thumbnail" style="--avatar-bg: var(--avatar-tone-{{ ($conversation->id % 5) + 1 }}); background: var(--app-secondary-soft);">
                            <span class="material-symbols-outlined" style="font-size: 24px; color: var(--app-secondary);">
                                {{ $isGroup ? 'group' : 'person' }}
                            </span>
                        </div>
                        <div class="post-card__body">
                            <span class="post-title">
                                @if ($isGroup)
                                    {{ $conversation->name ?: 'Group' }}
                                @else
                                    {{ $names ?: 'Conversation' }}
                                @endif
                            </span>
                            <div class="post-meta">
                                <span>{{ $names ?: 'Unknown' }}</span>
                                @if ($lastMsg)
                                    <span class="post-meta-sep">·</span>
                                    <span>{{ $lastMsg->created_at->diffForHumans() }}</span>
                                @endif
                            </div>
                            @if ($lastMsg)
                                <p class="post-excerpt">{{ Str::limit($lastMsg->body, 100) }}</p>
                            @else
                                <p class="post-excerpt" style="color: var(--app-text-muted);">No messages yet.</p>
                            @endif
                            <div class="post-actions">
                                <span class="post-action-btn">
                                    <span class="material-symbols-outlined">arrow_forward</span>
                                    Open
                                </span>
                            </div>
                        </div>
                    </a>
                </article>
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
        <section class="sidebar-card">
            <div class="sidebar-card__header">
                <span class="material-symbols-outlined">chat</span>
                <h2>About conversations</h2>
            </div>
            <p>Conversations are private — only the people you invite can see what's written here.</p>
            <div class="sidebar-stats">
                <span>{{ $conversations->total() }} conversations</span>
            </div>
        </section>

        <section class="sidebar-card">
            <div class="sidebar-card__header">
                <span class="material-symbols-outlined">lightbulb</span>
                <h2>Tips</h2>
            </div>
            <ol class="sidebar-rules">
                <li>Use direct messages for 1-to-1 chats</li>
                <li>Create a group to discuss with multiple people</li>
                <li>Topics stay until you archive them</li>
            </ol>
        </section>
    </aside>
</div>
@endsection
