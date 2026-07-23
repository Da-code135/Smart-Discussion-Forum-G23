@props(['topic'])

@php
    $topicId = is_object($topic) ? $topic->id : (is_array($topic) ? $topic['id'] : $topic);
    $topicTitle = is_object($topic) ? $topic->title : (is_array($topic) ? $topic['title'] : '');
    $shareUrl = route('forum.show', $topicId);
    $encodedShareUrl = urlencode($shareUrl);
    $shareText = urlencode($topicTitle);
    $menuId = 'share-menu-' . $topicId;
    $buttonId = 'share-btn-' . $topicId;
@endphp

<div style="position: relative; display: inline-flex;">
    <button type="button" class="post-action-btn" onclick="toggleShareMenu(event, '{{ $menuId }}')" id="{{ $buttonId }}">
        <span class="material-symbols-outlined">share</span>
        Share
    </button>
    <div id="{{ $menuId }}" class="share-menu" style="display: none; position: absolute; right: 0; top: calc(100% + 8px); z-index: 20;">
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
            @if (is_object($topic))
                <form action="{{ route('topics.share', $topic) }}" method="POST" class="form-stack">
                    @csrf
                    <div class="form-group">
                        <label for="expires_in_days_{{ $topicId }}" class="form-label">Link expiration</label>
                        <select name="expires_in_days" id="expires_in_days_{{ $topicId }}" class="form-input" required>
                            <option value="1">1 day</option>
                            <option value="3">3 days</option>
                            <option value="7">7 days</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Generate share link</button>
                </form>
                @if (session('share_url'))
                    <div class="form-group">
                        <label for="shareUrl_{{ $topicId }}" class="form-label">Your signed link</label>
                        <div class="form-actions-row">
                            <input type="text" id="shareUrl_{{ $topicId }}" class="form-input" value="{{ session('share_url') }}" readonly>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard(document.getElementById('shareUrl_{{ $topicId }}').value)">Copy</button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
