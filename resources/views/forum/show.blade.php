@extends('layouts.app')

@section('title', $topic->title)
@section('activeNav', 'topics')

@section('content')
{{-- Page Header & Navigation --}}
<header class="page-header">
    <div class="page-header-row">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('forum.index') }}" class="btn btn-tertiary btn-sm !px-2 !py-1">
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                </a>
                <span class="text-xs text-[var(--on-surface-variant)] opacity-60">
                    {{ $topic->group->group_name ?? 'Forum' }}
                </span>
                @if ($topic->post_type === 'question')
                    <span class="badge badge-secondary text-xs">Question</span>
                @endif
            </div>
        </div>
        <div class="flex gap-2 items-start">
            {{-- PDF Export Button --}}
            <a href="{{ route('forum.export-pdf', $topic->id) }}" class="btn btn-secondary btn-sm flex items-center gap-1 !px-3 !py-1.5 text-xs">
                <span class="material-symbols-outlined text-base">download</span>
                Export PDF
            </a>
            {{-- Social Sharing Dropdown --}}
            <div class="relative">
                <button onclick="toggleShareMenu()" class="btn btn-secondary btn-sm flex items-center gap-1 !px-3 !py-1.5 text-xs">
                    <span class="material-symbols-outlined text-base">share</span>
                    Share
                </button>
                <div id="share-menu" class="hidden absolute right-0 top-full mt-1 bg-white border border-black/10 rounded-lg shadow-lg min-w-[160px] z-[100] overflow-hidden">
                    <div class="px-4 py-2 text-xs text-[var(--on-surface-variant)] opacity-60 bg-[var(--surface-container-low)] border-b border-black/5">
                        Recipients must be logged in to view this topic.
                    </div>
                    <a href="https://wa.me/?text={{ urlencode('Check out this topic: ' . route('forum.show', $topic->id)) }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 no-underline text-[var(--on-surface)] text-sm border-b border-black/5 hover:bg-[var(--surface-container-low)]">
                        <span class="material-symbols-outlined text-lg text-[#25D366]">chat</span>
                        WhatsApp
                    </a>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('forum.show', $topic->id)) }}&text={{ urlencode('Check out: ' . $topic->title) }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 no-underline text-[var(--on-surface)] text-sm border-b border-black/5 hover:bg-[var(--surface-container-low)]">
                        <span class="material-symbols-outlined text-lg text-[#1DA1F2]">tag</span>
                        Twitter / X
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('forum.show', $topic->id)) }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 no-underline text-[var(--on-surface)] text-sm border-b border-black/5 hover:bg-[var(--surface-container-low)]">
                        <span class="material-symbols-outlined text-lg text-[#1877F2]">thumb_up</span>
                        Facebook
                    </a>
                    <button onclick="copyToClipboard('{{ route('forum.show', $topic->id) }}')" class="flex items-center gap-2 px-4 py-2.5 bg-transparent border-none w-full cursor-pointer text-[var(--on-surface)] text-sm hover:bg-[var(--surface-container-low)]">
                        <span class="material-symbols-outlined text-lg text-[var(--primary-sage)]">link</span>
                        Copy Link
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- Opening Post (Topic description) --}}
<article class="bento-card mb-6">
    <div class="flex items-start gap-3 mb-4">
        <div class="app-topbar-avatar shrink-0 !w-10 !h-10 !rounded-full !bg-[var(--secondary)] !text-white !flex !items-center !justify-center !font-semibold !text-sm">
            {{ collect(explode(' ', $topic->creator->full_name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') }}
        </div>
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <strong>{{ $topic->creator->full_name }}</strong>
                <span class="text-xs text-[var(--on-surface-variant)] opacity-50">
                    {{ $topic->created_at->format('M j, Y \a\t g:ia') }}
                </span>
            </div>
            <h1 class="mt-1 mb-3 text-2xl">{{ $topic->title }}</h1>
            <div class="text-[0.95rem] leading-relaxed text-black/85 whitespace-pre-wrap">
                {{ $topic->description }}
            </div>
        </div>
    </div>
</article>

{{-- Reply Count --}}
<div class="flex items-center justify-between mb-4">
    <h3 class="m-0 text-lg">
        Replies ({{ $topic->posts->count() }})
    </h3>
</div>

{{-- Replies List --}}
<section class="flex flex-col gap-3">
    @forelse ($topic->posts as $reply)
        <article class="bento-card p-4">
            <div class="flex items-start gap-3">
                <div class="app-topbar-avatar shrink-0 !w-9 !h-9 !rounded-full !bg-[var(--tertiary,#C49A6C)] !text-white !flex !items-center !justify-center !font-semibold !text-xs">
                    {{ collect(explode(' ', $reply->user->full_name))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('') }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <strong class="text-sm">{{ $reply->user->full_name }}</strong>
                        <span class="text-xs text-[var(--on-surface-variant)] opacity-50">
                            {{ $reply->created_at->format('M j, Y \a\t g:ia') }}
                            @if ($reply->created_at->ne($reply->updated_at))
                                &middot; edited
                            @endif
                        </span>
                    </div>
                    <div class="text-sm leading-relaxed text-black/85 whitespace-pre-wrap">
                        {{ $reply->content }}
                    </div>

                    {{-- Category badge if classified --}}
                    @if ($reply->category)
                        <div class="mt-2">
                            <span class="badge badge-secondary text-[0.7rem]">
                                {{ $reply->category->category_name }}
                            </span>
                        </div>
                    @endif

                    {{-- Exclude user form for post author --}}
                    @if ($reply->user_id === auth()->id())
                        <div class="mt-4 pt-4 border-t border-black/10">
                            <form method="POST" action="{{ route('forum.visibility.exclude', $reply->id) }}" class="exclude-form flex gap-2 items-center">
                                @csrf
                                <select name="user_id" class="py-1 px-2 border border-[#ccc] rounded text-sm">
                                    <option value="">Choose user to exclude...</option>
                                    @foreach ($excludableUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm !px-2 !py-1 text-xs">
                                    Exclude this user
                                </button>
                            </form>
                            @if(session('success') && session('post_id') == $reply->id)
                                <div class="alert alert-success mt-2 text-xs text-green-700">
                                    {{ session('success') }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="bento-card text-center py-10 px-8">
            <span class="material-symbols-outlined text-[2.5rem] text-[var(--secondary)] opacity-40">forum</span>
            <p class="mt-3 mb-0 text-[var(--on-surface-variant)] opacity-70">
                No replies yet. Be the first to respond!
            </p>
        </div>
    @endforelse
</section>

{{-- Reply Form --}}
<section class="mb-4 mt-6">
    <div class="bento-card p-5">
        <h3 class="m-0 mb-4 text-base">
            <span class="material-symbols-outlined text-xl align-middle mr-1">reply</span>
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

                <div class="form-group mb-4">
                    <textarea id="content"
                              name="content"
                              rows="4"
                              required
                              maxlength="10000"
                              placeholder="Write your reply..."
                              class="form-input @error('content') is-invalid @enderror">{{ old('content') }}</textarea>
                    @error('content')
                        <p class="form-error text-[var(--error,#dc3545)] text-sm mt-1">
                            <span class="material-symbols-outlined text-sm align-middle">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined text-xl">send</span>
                        Post Reply
                    </button>
                </div>
            </form>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
function toggleShareMenu() {
    const menu = document.getElementById('share-menu');
    menu.classList.toggle('hidden');
}

function copyToClipboard(url) {
    navigator.clipboard.writeText(url).then(function() {
        alert('Link copied to clipboard!');
    }).catch(function() {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = url;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Link copied to clipboard!');
    });
}

// Close share menu when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('share-menu');
    if (menu && !event.target.closest('#share-menu') && !event.target.closest('[onclick*="toggleShareMenu"]')) {
        menu.classList.add('hidden');
    }
});
</script>
@endpush