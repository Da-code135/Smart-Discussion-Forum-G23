@extends('layouts.app')

@section('title', 'Forum')
@section('activeNav', 'topics')

@section('content')
@php
    $user = Auth::user();
    $userInitials = collect(explode(' ', $user->full_name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
    $userAvatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][$user->id % 5];
@endphp

<div class="page-shell">
    <div class="page-shell__main page-stack">
        {{-- Community banner --}}
        <header class="page-header">
            <div class="page-header-row">
                <div>
                    <h1>{{ $group->group_name }}</h1>
                    <p>{{ $group->description ?: 'Browse focused group discussions or start a new topic.' }}</p>
                </div>
            </div>
        </header>

        {{-- Sort Tabs (decorative) --}}
        <div class="sort-tabs">
            <span class="sort-tab is-active">Hot</span>
            <span class="sort-tab">New</span>
            <span class="sort-tab">Top</span>
        </div>

        {{-- Create Post Card --}}
        <div class="create-post-card">
            <span class="app-topbar-avatar" style="--avatar-bg: {{ $userAvatarTone }}; width: 34px; height: 34px; font-size: 11px;">{{ $userInitials }}</span>
            <a href="{{ route('forum.create') }}" class="create-post-input">Create a post</a>
            <a href="{{ route('forum.create') }}" class="btn btn-primary">
                <span class="material-symbols-outlined">edit</span>
                <span class="hide-mobile">Create Post</span>
            </a>
        </div>

        {{-- Post list --}}
        <section class="topic-list">
            @forelse ($topics as $topic)
                @php
                    $initials = collect(explode(' ', optional($topic->creator)->full_name ?? 'Deleted User'))->map(fn ($word) => strtoupper(substr($word, 0, 1)))->take(2)->join('');
                    $avatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][($topic->creator->id ?? 0) % 5];
                @endphp
                <article class="post-card">
                    <div class="post-card__content">
                        <div class="post-thumbnail {{ $topic->post_type === 'question' ? 'post-thumbnail--question' : '' }}" style="--avatar-bg: {{ $avatarTone }};">
                            @if ($topic->post_type === 'question')
                                <span class="material-symbols-outlined">help</span>
                            @else
                                <span>{{ $initials }}</span>
                            @endif
                        </div>
                        <div class="post-card__body">
                            <a href="{{ route('forum.show', $topic->id) }}" class="post-title">{{ $topic->title }}</a>
                            <div class="post-meta">
                                Posted by <strong>{{ optional($topic->creator)->full_name ?? 'Deleted User' }}</strong>
                                <span class="post-meta-sep">·</span>
                                {{ $topic->created_at->diffForHumans() }}
                                @if ($topic->post_type === 'question')
                                    <span class="badge badge-secondary">Question</span>
                                @endif
                            </div>
                            <p class="post-excerpt">{{ Str::limit($topic->description, 150) }}</p>
                            <div class="post-actions">
                                <a href="{{ route('forum.show', $topic->id) }}" class="post-action-btn">
                                    <span class="material-symbols-outlined">chat_bubble</span>
                                    {{ $topic->posts_count }} {{ Str::plural('Comment', $topic->posts_count) }}
                                </a>
                                <a href="{{ route('forum.show', $topic->id) }}" class="post-action-btn">
                                    <span class="material-symbols-outlined">share</span>
                                    Share
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <span class="material-symbols-outlined" style="font-size: 40px;">forum</span>
                    <h2>No topics yet</h2>
                    <p>Start the first discussion for this group.</p>
                    <a href="{{ route('forum.create') }}" class="btn btn-primary">Create topic</a>
                </div>
            @endforelse
        </section>

        @if ($topics->hasPages())
            <section class="pagination-section">
                {{ $topics->links() }}
            </section>
        @endif
    </div>

    <aside class="page-shell__sidebar page-stack">
        <section class="sidebar-card">
            <div class="sidebar-card__header">
                <span class="material-symbols-outlined">folder</span>
                <h2>{{ $user->isSystemAdmin() ? 'All Groups' : $group->group_name }}</h2>
            </div>
            <p>{{ $user->isSystemAdmin() ? 'Browse discussions across all groups on the platform.' : ($group->description ?: 'This academic group uses Studdit for calm, structured discussion.') }}</p>
            <div class="sidebar-stats">
                <span>{{ $topics->total() }} {{ $user->isSystemAdmin() ? 'topics across all groups' : 'topics' }}</span>
            </div>
            <a href="{{ route('forum.create') }}" class="btn btn-primary btn-block" style="margin-top: 12px;">
                <span class="material-symbols-outlined">add</span>
                New Topic
            </a>
        </section>

        <section class="sidebar-card">
            <div class="sidebar-card__header">
                <span class="material-symbols-outlined">list_alt</span>
                <h2>Rules</h2>
            </div>
            <ol class="sidebar-rules">
                <li>Keep replies constructive, relevant, and academically respectful.</li>
                <li>No spam, self-promotion, or off-topic content.</li>
                <li>Respect all members and their perspectives.</li>
            </ol>
        </section>
    </aside>
</div>
@endsection
