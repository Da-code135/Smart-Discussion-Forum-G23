@extends('layouts.app')

@section('title', $topic->title)
@section('activeNav', 'topics')

@section('content')
{{-- Page Header & Navigation --}}
<header class="page-header">
    <div class="page-header-row">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <a href="{{ route('forum.index') }}" class="btn btn-tertiary btn-sm" style="padding: 0.25rem 0.5rem;">
                    <span class="material-symbols-outlined" style="font-size: 1rem;">arrow_back</span>
                </a>
                <span style="font-size: 0.8rem; color: rgba(88, 103, 75, 0.6);">
                    {{ $topic->group->group_name ?? 'Forum' }}
                </span>
                @if ($topic->post_type === 'question')
                    <span class="badge badge-secondary" style="font-size: 0.75rem;">Question</span>
                @endif
            </div>
        </div>
    </div>
</header>

{{-- Opening Post (Topic description) --}}
<article class="bento-card" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem;">
        <div class="app-topbar-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--secondary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; flex-shrink: 0;">
            {{ collect(explode(' ', $topic->creator->full_name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') }}
        </div>
        <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <strong>{{ $topic->creator->full_name }}</strong>
                <span style="font-size: 0.75rem; color: rgba(88, 103, 75, 0.5);">
                    {{ $topic->created_at->format('M j, Y \a\t g:ia') }}
                </span>
            </div>
            <h1 style="margin: 0.25rem 0 0.75rem; font-size: 1.5rem;">{{ $topic->title }}</h1>
            <div style="font-size: 0.95rem; line-height: 1.6; color: rgba(0,0,0,0.85); white-space: pre-wrap;">
                {{ $topic->description }}
            </div>
        </div>
    </div>
</article>

{{-- Reply Count --}}
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
    <h3 style="margin: 0; font-size: 1.1rem;">
        Replies ({{ $topic->posts->count() }})
    </h3>
</div>

{{-- Replies List --}}
<section style="display: flex; flex-direction: column; gap: 0.75rem;">
    @forelse ($topic->posts as $reply)
        <article class="bento-card" style="padding: 1rem 1.25rem;">
            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                <div class="app-topbar-avatar" style="width: 36px; height: 36px; border-radius: 50%; background: var(--tertiary, #C49A6C); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; flex-shrink: 0;">
                    {{ collect(explode(' ', $reply->user->full_name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') }}
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <strong style="font-size: 0.9rem;">{{ $reply->user->full_name }}</strong>
                        <span style="font-size: 0.75rem; color: rgba(88, 103, 75, 0.5);">
                            {{ $reply->created_at->format('M j, Y \a\t g:ia') }}
                            @if ($reply->created_at->ne($reply->updated_at))
                                &middot; edited
                            @endif
                        </span>
                    </div>
                    <div style="font-size: 0.9rem; line-height: 1.6; color: rgba(0,0,0,0.85); white-space: pre-wrap;">
                        {{ $reply->content }}
                    </div>

                    {{-- Category badge if classified --}}
                    @if ($reply->category)
                        <div style="margin-top: 0.5rem;">
                            <span class="badge badge-secondary" style="font-size: 0.7rem;">
                                {{ $reply->category->category_name }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="bento-card" style="text-align: center; padding: 2.5rem 2rem;">
            <span class="material-symbols-outlined" style="font-size: 2.5rem; color: var(--secondary); opacity: 0.4;">forum</span>
            <p style="margin: 0.75rem 0 0; color: rgba(88, 103, 75, 0.7);">
                No replies yet. Be the first to respond!
            </p>
        </div>
    @endforelse
</section>

{{-- Reply Form --}}
<section class="mb-4" style="margin-top: 1.5rem;">
    <div class="bento-card" style="padding: 1.25rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem;">
            <span class="material-symbols-outlined" style="font-size: 1.25rem; vertical-align: middle; margin-right: 0.25rem;">reply</span>
            Post a Reply
        </h3>

        @if ($topic->status !== 'active')
            <div class="alert alert-warning" role="alert">
                <span class="material-symbols-outlined">lock</span>
                This topic is closed for replies.
            </div>
        @else
            <form method="POST" action="{{ route('forum.reply.store', $topic->id) }}">
                @csrf

                <div class="form-group" style="margin-bottom: 1rem;">
                    <textarea id="content"
                              name="content"
                              rows="4"
                              required
                              maxlength="10000"
                              placeholder="Write your reply..."
                              class="form-input @error('content') is-invalid @enderror">{{ old('content') }}</textarea>
                    @error('content')
                        <p class="form-error" style="color: var(--danger, #dc3545); font-size: 0.875rem; margin-top: 0.25rem;">
                            <span class="material-symbols-outlined" style="font-size: 0.875rem; vertical-align: middle;">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined" style="font-size: 1.25rem;">send</span>
                        Post Reply
                    </button>
                </div>
            </form>
        @endif
    </div>
</section>
@endsection
