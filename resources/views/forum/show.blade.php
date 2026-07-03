@extends('layouts.app')

@section('title', $topic->title)
@section('activeNav', 'topics')

@php
    $shareUrl = route('forum.show', $topic->id);
    $encodedShareUrl = urlencode($shareUrl);
    $shareText = urlencode($topic->title);
@endphp

@section('content')
{{-- Page Header --}}
<header class="page-header">
    <div class="page-header-row">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <a href="{{ route('forum.index') }}" class="btn btn-tertiary btn-sm" style="padding: 0.25rem 0.5rem;">
                    <span class="material-symbols-outlined" style="font-size: 1rem;">arrow_back</span>
                </a>
                <span style="font-size: 0.75rem; color: var(--on-surface-variant); opacity: 0.6;">
                    {{ $topic->group->group_name ?? 'Forum' }}
                </span>
                @if ($topic->post_type !== 'discussion')
                    <span class="badge badge-secondary" style="font-size: 0.75rem;">{{ ucfirst($topic->post_type) }}</span>
                @endif
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
            <a href="{{ route('forum.export-pdf', $topic->id) }}" class="btn btn-secondary btn-sm">
                <span class="material-symbols-outlined" style="font-size: 1rem;">download</span>
                Export PDF
            </a>

            <div style="position: relative;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleShareMenu(event)">
                    <span class="material-symbols-outlined" style="font-size: 1rem;">share</span>
                    Share
                </button>

                <div id="share-menu" class="share-menu" style="display: none;">
                    <p style="margin: 0 0 0.75rem; font-size: 0.875rem; font-weight: 600;">Share this topic</p>

                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="https://wa.me/?text={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                            WhatsApp
                        </a>
                        <a href="https://twitter.com/intent/tweet?url={{ $encodedShareUrl }}&text={{ $shareText }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                            Twitter
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                            Facebook
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('{{ $shareUrl }}')">
                            Copy Link
                        </button>
                    </div>

                    <p style="margin: 0.75rem 0 0; font-size: 0.75rem; color: var(--on-surface-variant);">
                        Recipients must be logged in to view this topic.
                    </p>

                    <hr style="margin: 1rem 0; border: none; border-top: 1px solid var(--outline-variant);">

                    <p style="margin: 0 0 0.5rem; font-size: 0.8125rem; font-weight: 600;">Generate a temporary signed link</p>
                    <form action="{{ route('topics.share', $topic) }}" method="POST">
                        @csrf
                        <div class="form-group" style="margin-bottom: 0.75rem;">
                            <label for="expires_in_days" class="form-label">Link expiration</label>
                            <select name="expires_in_days" id="expires_in_days" class="form-input" required>
                                <option value="1">1 day</option>
                                <option value="3">3 days</option>
                                <option value="7">7 days</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Generate Share Link</button>
                    </form>

                    @if (session('share_url'))
                        <div style="margin-top: 0.75rem;">
                            <label for="shareUrl" class="form-label">Your shareable link</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="text" id="shareUrl" class="form-input" value="{{ session('share_url') }}" readonly>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="copySignedUrl()">Copy</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>

{{-- Opening Post --}}
<article class="bento-card" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
        <div class="app-topbar-avatar" style="width: 2.5rem; height: 2.5rem; flex-shrink: 0;">
            {{ collect(explode(' ', $topic->creator->full_name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') }}
        </div>
        <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <strong>{{ $topic->creator->full_name }}</strong>
                <span style="font-size: 0.75rem; color: var(--on-surface-variant); opacity: 0.5;">
                    {{ $topic->created_at->format('M j, Y \a\t g:ia') }}
                </span>
            </div>
            <h1 style="margin: 0.25rem 0 0.75rem; font-size: 1.5rem;">{{ $topic->title }}</h1>
            <div style="font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap;">{{ $topic->description }}</div>
        </div>
    </div>
</article>

{{-- Replies --}}
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
    <h3 style="margin: 0; font-size: 1.125rem;">Replies ({{ $topic->posts->count() }})</h3>
</div>

<section style="display: flex; flex-direction: column; gap: 0.75rem;">
    @forelse ($topic->posts as $reply)
        <article class="bento-card" style="padding: 1rem;">
            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                <div class="app-topbar-avatar" style="width: 2.25rem; height: 2.25rem; flex-shrink: 0;">
                    {{ collect(explode(' ', $reply->user->full_name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') }}
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <strong style="font-size: 0.875rem;">{{ $reply->user->full_name }}</strong>
                        <span style="font-size: 0.75rem; color: var(--on-surface-variant); opacity: 0.5;">
                            {{ $reply->created_at->format('M j, Y \a\t g:ia') }}
                            @if ($reply->created_at->ne($reply->updated_at))
                                &middot; edited
                            @endif
                        </span>
                    </div>
                    <div style="font-size: 0.875rem; line-height: 1.6; white-space: pre-wrap;">{{ $reply->content }}</div>

                    @if ($reply->category)
                        <div style="margin-top: 0.5rem;">
                            <span class="badge badge-secondary" style="font-size: 0.7rem;">{{ $reply->category->category_name }}</span>
                        </div>
                    @endif

                    @if ($reply->user_id === auth()->id())
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(0, 0, 0, 0.1);">
                            <form method="POST" action="{{ route('forum.visibility.exclude', $reply->id) }}" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                @csrf
                                <select name="user_id" class="form-input" style="padding: 0.25rem 0.5rem; font-size: 0.875rem; max-width: 14rem;" required>
                                    <option value="">Choose user to exclude...</option>
                                    @foreach ($excludableUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm">Exclude this user</button>
                            </form>
                            @if (session('success') && session('post_id') == $reply->id)
                                <p style="margin: 0.5rem 0 0; font-size: 0.75rem; color: var(--secondary);">{{ session('success') }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="bento-card" style="text-align: center; padding: 2.5rem 2rem;">
            <span class="material-symbols-outlined" style="font-size: 2.5rem; color: var(--secondary); opacity: 0.4;">forum</span>
            <p style="margin: 0.75rem 0 0; color: var(--on-surface-variant); opacity: 0.7;">
                No replies yet. Be the first to respond!
            </p>
        </div>
    @endforelse
</section>

{{-- Reply Form --}}
<section style="margin: 1.5rem 0 1rem;">
    <div class="bento-card" style="padding: 1.25rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem;">
            <span class="material-symbols-outlined" style="font-size: 1.25rem; vertical-align: middle;">reply</span>
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
                        <p class="form-error" style="color: var(--error); font-size: 0.875rem; margin-top: 0.25rem;">{{ $message }}</p>
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

<div class="topic-actions">
    @can('update', $topic)
        <a href="{{ route('topics.edit', $topic) }}" class="btn btn-primary">Edit Topic</a>
    @endcan
    
    @auth
        <x-report-button type="topic" :id="$topic->id" />
    @endauth
</div>

@endsection

@push('styles')
<style>
    .share-menu {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        z-index: 20;
        min-width: 16rem;
        padding: 1rem;
        background: var(--surface-container-lowest, #fff);
        border: 1px solid var(--outline-variant);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-md);
    }
</style>
@endpush

@push('scripts')
<script>
    function toggleShareMenu(event) {
        event.stopPropagation();
        const menu = document.getElementById('share-menu');
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }

    function copyToClipboard(url) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                alert('Link copied to clipboard!');
            }).catch(function () {
                fallbackCopy(url);
            });
        } else {
            fallbackCopy(url);
        }
    }

    function copySignedUrl() {
        const input = document.getElementById('shareUrl');
        if (input) {
            copyToClipboard(input.value);
        }
    }

    function fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Link copied to clipboard!');
    }

    document.addEventListener('click', function (event) {
        const menu = document.getElementById('share-menu');
        if (menu && menu.style.display !== 'none'
            && !event.target.closest('#share-menu')
            && !event.target.closest('[onclick*="toggleShareMenu"]')) {
            menu.style.display = 'none';
        }
    });
</script>
@endpush
