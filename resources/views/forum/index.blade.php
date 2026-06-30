@extends('layouts.app')

@section('title', 'Forum')
@section('activeNav', 'topics')

@section('content')
@php $group = Auth::user()->group; @endphp

{{-- Page Header --}}
<header class="page-header">
    <div class="page-header-row">
        <div>
            <h1>{{ $group?->group_name ?? 'My Group' }} Forum</h1>
            <p>Browse discussions in your group or start a new topic</p>
        </div>
        <a href="{{ route('forum.create') }}" class="btn btn-primary">
            <span class="material-symbols-outlined" style="font-size: 1.25rem;">add</span>
            New Topic
        </a>
    </div>
</header>

{{-- Topic List --}}
<section class="topics-list" style="display: flex; flex-direction: column; gap: 0.75rem;">
    @forelse ($topics as $topic)
        <a href="{{ route('forum.show', $topic->id) }}" class="discussion-item" style="text-decoration: none;">
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <h3 style="margin: 0;">{{ $topic->title }}</h3>
                    @if ($topic->post_type === 'question')
                        <span class="badge badge-secondary" style="font-size: 0.75rem;">Question</span>
                    @endif
                </div>
                <p style="margin: 0.25rem 0 0; color: rgba(88, 103, 75, 0.8); font-size: 0.875rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                    {{ Str::limit($topic->description, 150) }}
                </p>
                <div class="discussion-meta" style="margin-top: 0.5rem;">
                    <span>Posted by {{ $topic->creator->full_name }}</span>
                    <span class="discussion-meta-dot"></span>
                    <span>{{ $topic->posts_count }} {{ Str::plural('reply', $topic->posts_count) }}</span>
                    <span class="discussion-meta-dot"></span>
                    <span>{{ $topic->created_at->diffForHumans() }}</span>
                </div>
            </div>
            <span class="discussion-action">
                <span class="material-symbols-outlined" style="font-size: 1.125rem;">arrow_forward</span>
            </span>
        </a>
    @empty
        <div class="bento-card" style="text-align: center; padding: 3rem 2rem;">
            <span class="material-symbols-outlined" style="font-size: 3rem; color: var(--secondary); opacity: 0.5;">forum</span>
            <h3 style="margin: 1rem 0 0.5rem;">No topics yet</h3>
            <p style="color: rgba(88, 103, 75, 0.7); margin: 0 0 1.5rem;">
                Be the first to start a discussion in your group!
            </p>
            <a href="{{ route('forum.create') }}" class="btn btn-primary">
                <span class="material-symbols-outlined" style="font-size: 1.25rem;">add</span>
                Create Your First Topic
            </a>
        </div>
    @endforelse
</section>

{{-- Pagination --}}
@if ($topics->hasPages())
    <section class="pagination-section" style="margin-top: 1.5rem;">
        {{ $topics->links() }}
    </section>
@endif
@endsection
