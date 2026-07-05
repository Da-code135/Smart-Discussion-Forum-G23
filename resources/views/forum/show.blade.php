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
    <header class="page-header">
        <div class="page-header-row">
            <div class="page-stack">
                <div class="discussion-meta">
                    <a href="{{ route('forum.index') }}" class="back-link">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back to forum
                    </a>
                    <span class="discussion-meta-dot"></span>
                    <span>{{ $topic->group->group_name ?? 'Forum' }}</span>
                    @if ($topic->post_type !== 'discussion')
                        <span class="badge badge-secondary">{{ ucfirst($topic->post_type) }}</span>
                    @endif
                </div>
                <h1>{{ $topic->title }}</h1>
            </div>

            <div class="topic-actions">
                @if ($topic->created_by === auth()->id() || auth()->user()->isAdmin())
                    <a href="{{ route('forum.edit', $topic->id) }}" class="btn btn-secondary btn-sm">
                        <span class="material-symbols-outlined">edit</span>
                        Edit
                    </a>
                @endif

                <a href="{{ route('forum.export-pdf', $topic->id) }}" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-outlined">download</span>
                    Export PDF
                </a>

                <div style="position: relative;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleShareMenu(event)">
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
            </div>
        </div>
    </header>

    <article class="topic-hero">
        <div class="topic-hero__header">
            <div class="app-topbar-avatar" style="--avatar-bg: {{ $creatorAvatarTone }}; width: 56px; height: 56px; font-size: 18px;">{{ $creatorInitials }}</div>
            <div class="page-stack">
                <div class="topic-meta">
                    <strong>{{ optional($topic->creator)->full_name ?? 'Deleted User' }}</strong>
                    <span class="discussion-meta-dot"></span>
                    <span>{{ $topic->created_at->format('M j, Y \a\t g:ia') }}</span>
                </div>
                <div style="white-space: pre-wrap;">{{ $topic->description }}</div>
            </div>
        </div>
    </article>

    <section class="page-stack">
        <div class="section-header">
            <div>
                <h2>Replies</h2>
                <p>{{ $posts->total() }} {{ Str::plural('reply', $posts->total()) }}</p>
            </div>
        </div>

        @if ($posts->isEmpty())
            <div class="empty-state">
                <span class="material-symbols-outlined" style="font-size: 40px;">forum</span>
                <p>No replies yet. Be the first to respond.</p>
            </div>
        @else
            <div class="reply-stream">
                @foreach ($posts as $reply)
                    @php
                        $replyInitials = collect(explode(' ', optional($reply->user)->full_name ?? 'Deleted User'))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                        $replyAvatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][($reply->user->id ?? 0) % 5];
                    @endphp
                    <article class="reply-item">
                        <div class="app-topbar-avatar" style="--avatar-bg: {{ $replyAvatarTone }}; width: 44px; height: 44px;">{{ $replyInitials }}</div>
                        <div class="page-stack">
                            <div class="reply-meta">
                                <strong>{{ optional($reply->user)->full_name ?? 'Deleted User' }}</strong>
                                <span class="discussion-meta-dot"></span>
                                <span>{{ $reply->created_at->format('M j, Y \a\t g:ia') }}</span>
                                @if ($reply->created_at->ne($reply->updated_at))
                                    <span class="discussion-meta-dot"></span>
                                    <span>edited</span>
                                @endif
                            </div>

                            <div style="white-space: pre-wrap;">{{ $reply->content }}</div>

                            @if ($reply->category)
                                <div>
                                    <span class="badge badge-secondary">{{ $reply->category->category_name }}</span>
                                </div>
                            @endif

                            @if ($reply->user_id === auth()->id())
                                <div class="reply-actions">
                                    <form method="POST" action="{{ route('forum.visibility.exclude', $reply->id) }}" class="form-actions-row">
                                        @csrf
                                        <select name="user_id" class="form-input" style="max-width: 240px;" required>
                                            <option value="">Choose user to exclude</option>
                                            @foreach ($excludableUsers as $user)
                                                <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-secondary btn-sm">Exclude user</button>
                                    </form>
                                    @if (session('success') && session('post_id') == $reply->id)
                                        <p class="meta-text">{{ session('success') }}</p>
                                    @endif
                                </div>
                            @endif
                            <div style="margin-top: 0.5rem;">
                                <x-report-button type="post" :id="$reply->id" />
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pagination-shell">
                {{ $posts->links() }}
            </div>
        @endif
    </section>

    <section class="reply-composer page-stack">
        <div>
            <h2>Post a reply</h2>
            <p>Keep your response constructive, clear, and relevant to the thread.</p>
        </div>

        @if ($topic->status !== 'active')
            <div class="alert alert-warning" role="alert">
                <span class="material-symbols-outlined">lock</span>
                <span>This topic is closed for replies.</span>
            </div>
        @else
            <form method="POST" action="{{ route('forum.reply.store', $topic->id) }}" class="form-stack">
                @csrf
                <div class="form-group">
                    <textarea id="content" name="content" rows="5" required maxlength="10000" placeholder="Write your reply..." class="form-input @error('content') is-invalid @enderror">{{ old('content') }}</textarea>
                    @error('content')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="topic-footer-actions" style="justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">send</span>
                        Post reply
                    </button>
                </div>
            </form>
        @endif
    </section>

    @auth
        <div class="topic-actions">
            <x-report-button type="topic" :id="$topic->id" />
        </div>
    @endauth
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
