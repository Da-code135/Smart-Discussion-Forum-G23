@extends('layouts.app')

@section('title', $topic->title)
@section('activeNav', 'topics')

@php
    $shareUrl = route('forum.show', $topic->id);
    $encodedShareUrl = urlencode($shareUrl);
    $shareText = urlencode($topic->title);
    $creatorInitials = collect(explode(' ', optional($topic->creator)->full_name ?? 'Deleted User'))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
    $creatorAvatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][($topic->creator->id ?? 0) % 5];
@endphp

@section('content')
<div class="page-stack">
    {{-- Back link --}}
    <a href="{{ route('forum.index') }}" class="back-link" style="display: inline-flex; align-items: center; gap: 6px; width: fit-content;">
        <span class="material-symbols-outlined">arrow_back</span>
        Back to {{ $topic->group->group_name ?? 'Forum' }}
    </a>

    {{-- OP Post Card (expanded) --}}
    <article class="post-card post-card--expanded">
        <div class="post-card__content">
            <div class="post-thumbnail {{ $topic->post_type === 'question' ? 'post-thumbnail--question' : '' }}" style="--avatar-bg: {{ $creatorAvatarTone }};">
                @if ($topic->post_type === 'question')
                    <span class="material-symbols-outlined">help</span>
                @else
                    <span>{{ $creatorInitials }}</span>
                @endif
            </div>
            <div class="post-card__body">
                <div class="post-meta">
                    <span>{{ $topic->group->group_name ?? 'Forum' }}</span>
                    @if ($topic->post_type !== 'discussion')
                        <span class="post-meta-sep">·</span>
                        <span class="badge badge-secondary">{{ ucfirst($topic->post_type) }}</span>
                    @endif
                </div>
                <h1 class="post-title post-title--expanded">{{ $topic->title }}</h1>
                <div class="post-meta">
                    Posted by <strong>{{ optional($topic->creator)->full_name ?? 'Deleted User' }}</strong>
                    <span class="post-meta-sep">·</span>
                    {{ $topic->created_at->format('M j, Y \a\t g:ia') }}
                </div>
                <div class="post-body">{{ $topic->description }}</div>
                <div class="post-actions">
                    @if ($topic->created_by === auth()->id() || auth()->user()->isAdmin())
                        <a href="{{ route('forum.edit', $topic->id) }}" class="post-action-btn">
                            <span class="material-symbols-outlined">edit</span>
                            Edit
                        </a>
                    @endif
                    <a href="{{ route('forum.export-pdf', $topic->id) }}" class="post-action-btn">
                        <span class="material-symbols-outlined">download</span>
                        Export PDF
                    </a>
                    <div style="position: relative; display: inline-flex;">
                        <button type="button" class="post-action-btn" onclick="toggleShareMenu(event)">
                            <span class="material-symbols-outlined">share</span>
                            Share
                        </button>
                        <div id="share-menu" class="share-menu" style="display: none; position: absolute; right: 0; top: calc(100% + 8px); z-index: 20;">
                            <div class="page-stack">
                                <div>
                                    <h2>Share this topic</h2>
                                    <p>Recipients must be logged in unless you generate a signed link.</p>
                                </div>
                                <div class="share-menu__links">
                                    <a href="https://wa.me/?text={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">WhatsApp</a>
                                    <a href="https://twitter.com/intent/tweet?url={{ $encodedShareUrl }}&text={{ $shareText }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">Twitter</a>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedShareUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">Facebook</a>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('{{ $shareUrl }}')">Copy link</button>
                                </div>
                                <form action="{{ route('topics.share', $topic) }}" method="POST" class="form-stack">
                                    @csrf
                                    <div class="form-group">
                                        <label for="expires_in_days" class="form-label">Link expiration</label>
                                        <select name="expires_in_days" id="expires_in_days" class="form-input" required>
                                            <option value="1">1 day</option>
                                            <option value="3">3 days</option>
                                            <option value="7">7 days</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Generate share link</button>
                                </form>
                                @if (session('share_url'))
                                    <div class="form-group">
                                        <label for="shareUrl" class="form-label">Your signed link</label>
                                        <div class="form-actions-row">
                                            <input type="text" id="shareUrl" class="form-input" value="{{ session('share_url') }}" readonly>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="copySignedUrl()">Copy</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @auth
                        <x-report-button type="topic" :id="$topic->id" />
                    @endauth
                </div>
            </div>
        </div>
    </article>

    {{-- Comments Section --}}
    <section class="comments-section">
        <div class="comments-header">
            <h2>Comments ({{ $posts->total() }})</h2>
        </div>

        @if ($posts->isEmpty())
            <div class="empty-state" style="padding: 24px; border: 0; box-shadow: none;">
                <span class="material-symbols-outlined" style="font-size: 36px;">forum</span>
                <p>No comments yet. Be the first to respond.</p>
            </div>
        @else
            @foreach ($posts as $reply)
                @php
                    $replyInitials = collect(explode(' ', optional($reply->user)->full_name ?? 'Deleted User'))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                    $replyAvatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][($reply->user->id ?? 0) % 5];
                @endphp
                <article class="comment">
                    <div class="comment__header">
                        <span class="app-topbar-avatar" style="--avatar-bg: {{ $replyAvatarTone }}; width: 28px; height: 28px; font-size: 0.6rem;">{{ $replyInitials }}</span>
                        <strong>{{ optional($reply->user)->full_name ?? 'Deleted User' }}</strong>
                        <span class="post-meta-sep">·</span>
                        {{ $reply->created_at->format('M j, Y \a\t g:ia') }}
                        @if ($reply->created_at->ne($reply->updated_at))
                            <span class="post-meta-sep">·</span>
                            <em>edited</em>
                        @endif
                    </div>
                    <div class="comment__content">{{ $reply->content }}</div>

                    @if ($reply->category)
                        <div>
                            <span class="badge badge-secondary">{{ $reply->category->category_name }}</span>
                        </div>
                    @endif

                    <div class="comment__actions">
                        @if ($reply->user_id === auth()->id())
                            <form method="POST" action="{{ route('forum.visibility.exclude', $reply->id) }}" class="form-actions-row" style="gap: 6px;">
                                @csrf
                                <select name="user_id" class="form-input" style="width: auto; min-width: 140px; min-height: 30px; padding: 4px 8px; font-size: 12px;" required>
                                    <option value="">Exclude user...</option>
                                    @foreach ($excludableUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm" style="min-height: 30px; padding: 4px 10px;">Exclude</button>
                            </form>
                            @if (session('success') && session('post_id') == $reply->id)
                                <p class="meta-text">{{ session('success') }}</p>
                            @endif
                        @endif
                        <x-report-button type="post" :id="$reply->id" />
                    </div>
                </article>
            @endforeach

            <div class="pagination-shell" style="margin-top: 12px;">
                {{ $posts->links() }}
            </div>
        @endif
    </section>

    {{-- Reply Composer --}}
    @if ($topic->status !== 'active')
        <div class="alert alert-warning" role="alert">
            <span class="material-symbols-outlined">lock</span>
            <span>This topic is closed for replies.</span>
        </div>
    @else
        <section class="reply-composer">
            <div class="reply-composer__header">
                <span class="material-symbols-outlined">add_comment</span>
                <h2>Post a comment</h2>
            </div>
            <form method="POST" action="{{ route('forum.reply.store', $topic->id) }}">
                @csrf
                <div class="form-group">
                    <textarea id="content" name="content" rows="4" required maxlength="10000" placeholder="What are your thoughts?" class="form-input @error('content') is-invalid @enderror">{{ old('content') }}</textarea>
                    @error('content')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="composer-footer">
                    <span class="form-hint">Keep your response constructive, clear, and relevant</span>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">send</span>
                        Comment
                    </button>
                </div>
            </form>
        </section>
    @endif
</div>
@endsection

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
        if (menu && menu.style.display !== 'none' && !event.target.closest('#share-menu') && !event.target.closest('[onclick*="toggleShareMenu"]')) {
            menu.style.display = 'none';
        }
    });
</script>
@endpush
