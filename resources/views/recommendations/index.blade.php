@extends('layouts.app')

@section('title', 'Recommended for You')

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack" style="max-width: 720px; margin: 0 auto;">

        <header class="page-header">
            <div class="page-header-row">
                <div>
                    <h1>Recommended for You</h1>
                    <p>Topics similar to ones you've engaged with</p>
                </div>
            </div>
        </header>

        <section style="display: flex; flex-direction: column; gap: 0.75rem;">
            @forelse ($recommendations as $topic)
                <a href="{{ route('forum.show', $topic->id) }}"
                   class="bento-card"
                   style="text-decoration: none; padding: 1rem; display: block;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                            <h3 style="margin: 0;">{{ $topic->title }}</h3>
                            @if ($topic->category)
                                <span class="badge badge-secondary" style="font-size: 0.7rem;">
                                    {{ $topic->category->category_name }}
                                </span>
                            @endif
                            @if ($topic->post_type === 'question')
                                <span class="badge badge-secondary" style="font-size: 0.7rem;">Question</span>
                            @endif
                        </div>
                        <p style="margin: 0.25rem 0 0; color: rgba(88, 103, 75, 0.8); font-size: 0.875rem;">
                            {{ Str::limit($topic->description, 150) }}
                        </p>
                        <div class="discussion-meta" style="margin-top: 0.5rem;">
                            <span>Posted by {{ $topic->creator->full_name ?? 'Deleted User' }}</span>
                            <span class="discussion-meta-dot"></span>
                            <span>{{ $topic->posts_count }} {{ $topic->posts_count === 1 ? 'reply' : 'replies' }}</span>
                            <span class="discussion-meta-dot"></span>
                            <span>{{ $topic->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="bento-card" style="text-align: center; padding: 2rem;">
                    <span class="material-symbols-outlined" style="font-size: 2.5rem; opacity: 0.4;">lightbulb</span>
                    <p style="margin: 1rem 0 0; color: rgba(88, 103, 75, 0.7);">
                        No recommendations yet. Participate in more discussions to get personalized recommendations!
                    </p>
                    <a href="{{ route('forum.index') }}" class="btn btn-primary" style="margin-top: 1rem;">
                        Browse Topics
                    </a>
                </div>
            @endforelse
        </section>

    </div>
</div>
@endsection
