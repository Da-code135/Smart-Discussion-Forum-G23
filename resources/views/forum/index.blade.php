@extends('layouts.app')

@section('title', 'Forum')
@section('activeNav', 'topics')

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack">
        <header class="page-header">
            <div class="page-header-row">
                <div>
                    <h1>{{ $group->group_name }} forum</h1>
                    <p>Browse focused group discussions or start a new topic.</p>
                </div>
                <a href="{{ route('forum.create') }}" class="btn btn-primary">
                    <span class="material-symbols-outlined">add</span>
                    New topic
                </a>
            </div>
        </header>

        <section class="topic-list">
            @forelse ($topics as $topic)
                @php
                    $initials = collect(explode(' ', $topic->creator->full_name))->map(fn ($word) => strtoupper(substr($word, 0, 1)))->take(2)->join('');
                    $avatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][$topic->creator->id % 5];
                @endphp
                <a href="{{ route('forum.show', $topic->id) }}" class="discussion-item">
                    <div class="app-topbar-avatar" style="--avatar-bg: {{ $avatarTone }};">{{ $initials }}</div>
                    <div class="topic-row__body">
                        <div class="discussion-meta">
                            <span>{{ $topic->creator->full_name }}</span>
                            <span class="discussion-meta-dot"></span>
                            <span>{{ $topic->created_at->diffForHumans() }}</span>
                            @if ($topic->post_type === 'question')
                                <span class="badge badge-secondary">Question</span>
                            @endif
                        </div>
                        <h3>{{ $topic->title }}</h3>
                        <p class="topic-row__excerpt">{{ Str::limit($topic->description, 150) }}</p>
                        <div class="discussion-meta">
                            <span>{{ $topic->posts_count }} {{ Str::plural('reply', $topic->posts_count) }}</span>
                        </div>
                    </div>
                    <span class="section-link">Open <span class="material-symbols-outlined">arrow_forward</span></span>
                </a>
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
        <section class="sidebar-card page-stack">
            <div>
                <h2>About this group</h2>
                <p>{{ $group->description ?: 'This academic group uses Studdit for calm, structured discussion.' }}</p>
            </div>
            <div class="profile-summary-list">
                <span class="badge badge-secondary">{{ $topics->total() }} topics</span>
                <span class="badge badge-success">Active group</span>
            </div>
        </section>

        <section class="sidebar-card page-stack">
            <h2>Forum rules</h2>
            <p>Keep replies constructive, relevant, and academically respectful. There are no votes or scores here—only discussion.</p>
        </section>
    </aside>
</div>
@endsection
