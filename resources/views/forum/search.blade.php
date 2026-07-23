@extends('layouts.app')

@section('title', 'Search: ' . $query)
@section('activeNav', 'topics')

@section('content')
@php
    $user = Auth::user();
@endphp

<div class="page-shell">
    <div class="page-shell__main page-stack">
        <header class="page-header">
            <div class="page-header-row">
                <div>
                    <h1>Search results</h1>
                    <p>
                        @if ($topics->total() > 0)
                            Found {{ $topics->total() }} {{ Str::plural('result', $topics->total()) }} for <strong>"{{ $query }}"</strong>
                        @else
                            No results for <strong>"{{ $query }}"</strong>
                        @endif
                    </p>
                </div>
                <a href="{{ route('forum.index') }}" class="back-link">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Back to forum
                </a>
            </div>
        </header>

        {{-- Search form inline --}}
        <form method="GET" action="{{ route('forum.search') }}" class="create-post-card" style="margin-bottom: 0;">
            <span class="material-symbols-outlined" style="color: var(--app-text-muted);">search</span>
            <input
                type="text"
                name="q"
                value="{{ $query }}"
                placeholder="Search topics..."
                class="create-post-input"
                style="cursor: text;"
                autofocus
            >
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined">search</span>
                Search
            </button>
        </form>

        {{-- Results --}}
        <section class="topic-list">
            @forelse ($topics as $topic)
                @php
                    $initials = collect(explode(' ', optional($topic->creator)->full_name ?? 'Deleted User'))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
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
                                <span class="post-meta-sep">·</span>
                                <span>{{ $topic->group->group_name ?? 'General' }}</span>
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
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">
                    <span class="material-symbols-outlined" style="font-size: 40px;">search_off</span>
                    <h2>No results found</h2>
                    <p>Try a different search term or browse the forum.</p>
                    <a href="{{ route('forum.index') }}" class="btn btn-primary">Browse forum</a>
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
                <span class="material-symbols-outlined">search</span>
                <h2>Search tips</h2>
            </div>
            <ol class="sidebar-rules">
                <li>Search matches topic titles and descriptions</li>
                <li>Results are scoped to your accessible groups</li>
                <li>Try shorter or more general terms for better results</li>
            </ol>
        </section>

        <section class="sidebar-card">
            <div class="sidebar-card__header">
                <span class="material-symbols-outlined">folder</span>
                <h2>{{ $user->isSystemAdmin() ? 'All Groups' : ($group->group_name ?? 'General') }}</h2>
            </div>
            <p>{{ $user->isSystemAdmin() ? 'Results span all groups across the platform.' : ($group->description ?? 'Academic discussion group.') }}</p>
            <a href="{{ route('forum.create') }}" class="btn btn-primary btn-block" style="margin-top: 12px;">
                <span class="material-symbols-outlined">add</span>
                New Topic
            </a>
        </section>
    </aside>
</div>
@endsection
