@extends('layouts.app')

@section('title', $conversation->name ?? 'Conversation')
@section('activeNav', 'conversations')

@section('content')
<div class="page-shell">
    <main class="page-shell__content page-stack">
        <!-- Conversation Header -->
        <header class="conversation-header">
            <div class="conversation-info">
                <h1>{{ $conversation->name ?? ($conversation->type === 'direct' ? 'Direct Message' : 'Group Conversation') }}</h1>
                <p class="conversation-meta">
                    {{ $conversation->type === 'direct' ? 'Direct message' : 'Group conversation' }} with 
                    @foreach($conversation->participants as $participant)
                        {{ $participant->full_name }}{{ !$loop->last ? ', ' : '' }}
                    @endforeach
                </p>
            </div>
        </header>

        <!-- Messages Container -->
        <div class="conversation-messages" id="messages-container">
            <!-- Messages will be loaded here -->
            <div id="messages-list" class="messages-list">
                @forelse($messages as $message)
                    @php
                        $isMine = $message->sender_id === auth()->id();
                    @endphp
                    <div class="message-item {{ $isMine ? 'message-item--mine' : 'message-item--theirs' }}" data-message-id="{{ $message->id }}">
                        @if (!$isMine)
                            <span class="message-sender-name">{{ $message->sender->full_name }}</span>
                        @endif
                        <div class="message-bubble">
                            <div class="message-body">{{ $message->body }}</div>
                            <div class="message-footer">
                                <span class="message-time">{{ $message->created_at->format('g:i A') }}</span>
                                @if ($isMine)
                                    <span class="message-status message-status--{{ $message->delivery_status }}" title="{{ $message->delivery_status_label }}">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">{{ $message->delivery_status_icon }}</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="no-messages">
                        <p>No messages yet. Be the first to send one!</p>
                    </div>
                @endforelse
            </div>
            
            <!-- Pagination links for older messages -->
            @if($messages->hasMorePages())
                <div class="load-more-messages">
                    <button id="load-older-btn" class="btn btn-secondary">Load Older Messages</button>
                </div>
            @endif
        </div>

        <!-- Message Input Form -->
        <div class="conversation-input">
            <form id="message-form" action="{{ route('conversations.messages.store', $conversation->id) }}" method="POST">
                @csrf
                <div class="input-group">
                    <textarea 
                        id="message-input" 
                        name="body" 
                        placeholder="Type your message..." 
                        maxlength="10000"
                        required
                        class="form-control message-textarea"
                    ></textarea>
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </main>

    <aside class="page-shell__sidebar page-stack">
        <section class="sidebar-card page-stack">
            <h2>Participants</h2>
            <ul class="participant-list">
                @foreach($conversation->participants as $participant)
                    <li class="participant-item">
                        <span class="participant-name">{{ $participant->full_name }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    </aside>
</div>

{{-- Echo is already initialized in app.js via Vite --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const conversationId = {{ $conversation->id }};
    const userId = {{ auth()->id() }};
    
    // Listen for new messages via Echo (already initialized in app.js)
    if (window.Echo) {
        Echo.private(`conversation.${conversationId}`)
            .listen('MessageSent', (e) => {
                appendMessage(e);
            })
            .listen('MessageDelivered', (e) => {
                updateMessageStatus(e.message_id, 'delivered');
            })
            .listen('MessagesRead', (e) => {
                if (e.read_by_user_id != userId) {
                    markAllMyMessagesAsRead();
                }
            });
    }
    
    // Function to append a new message to the chat view
    function appendMessage(messageData) {
        const messagesList = document.getElementById('messages-list');
        const isMine = messageData.sender_id == userId;
        
        const messageElement = document.createElement('div');
        messageElement.className = 'message-item ' + (isMine ? 'message-item--mine' : 'message-item--theirs');
        messageElement.dataset.messageId = messageData.id;
        
        let senderHtml = '';
        if (!isMine) {
            senderHtml = `<span class="message-sender-name">${escapeHtml(messageData.sender_name)}</span>`;
        }
        
        const time = formatTime(messageData.created_at);
        const statusIcon = isMine ? `<span class="message-status message-status--sent" title="Sent"><span class="material-symbols-outlined" style="font-size: 14px;">done</span></span>` : '';
        
        messageElement.innerHTML = `
            ${senderHtml}
            <div class="message-bubble">
                <div class="message-body">${escapeHtml(messageData.body)}</div>
                <div class="message-footer">
                    <span class="message-time">${time}</span>
                    ${statusIcon}
                </div>
            </div>
        `;
        
        messagesList.appendChild(messageElement);
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    // Format time nicely
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString(undefined, {
            hour: 'numeric',
            minute: '2-digit'
        });
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Scroll to bottom of messages
    function scrollToBottom() {
        const container = document.getElementById('messages-container');
        container.scrollTop = container.scrollHeight;
    }
    
    // Handle form submission
    const messageForm = document.getElementById('message-form');
    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(messageForm);
        const messageBody = formData.get('body').trim();
        
        if (!messageBody) {
            return;
        }
        
        // Disable form temporarily
        const submitBtn = messageForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        
        // Get the Echo socket ID so the server can exclude us from the broadcast
        const socketId = window.Echo ? window.Echo.socketId() : null;
        
        const headers = {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        // Send the socket ID so broadcast()->toOthers() works correctly
        if (socketId) {
            headers['X-Socket-ID'] = socketId;
        }

        // Submit via AJAX
        fetch(messageForm.getAttribute('action'), {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                body: messageBody
            })
        })
        .then(response => {
            if (response.ok) {
                // Clear the input
                messageForm.reset();
                // Parse the JSON response and append the message immediately
                // (broadcast()->toOthers() excludes us, so Echo won't add a duplicate)
                return response.json().then(data => {
                    if (data && data.data) {
                        appendMessage(data.data);
                    }
                }).catch(() => {
                    // JSON parse failed — still ok, message is in DB
                });
            } else {
                // Try to parse error message from response
                response.json().then(data => {
                    showError(data.message || 'Failed to send message. Please try again.');
                }).catch(() => {
                    showError('Failed to send message. Please try again.');
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('An error occurred while sending your message.');
        })
        .finally(() => {
            // Re-enable form
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
    
    // Update a single message's delivery status icon
    function updateMessageStatus(messageId, status) {
        const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageEl) return;

        const statusEl = messageEl.querySelector('.message-status');
        if (statusEl) {
            statusEl.className = `message-status message-status--${status}`;
            statusEl.title = status.charAt(0).toUpperCase() + status.slice(1);
            const icon = statusEl.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = status === 'read' ? 'done_all' : 'done';
            }
        }
    }

    // Mark all of the current user's visible messages as read
    function markAllMyMessagesAsRead() {
        document.querySelectorAll('.message-item--mine .message-status').forEach((statusEl) => {
            statusEl.className = 'message-status message-status--read';
            statusEl.title = 'Read';
            const icon = statusEl.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.textContent = 'done_all';
            }
        });
    }

    // Show error message in the UI
    function showError(message) {
        // Remove any existing error
        const existing = document.getElementById('message-error');
        if (existing) existing.remove();

        const errorEl = document.createElement('div');
        errorEl.id = 'message-error';
        errorEl.style.cssText = 'padding: 8px 12px; margin-bottom: 8px; background: var(--app-danger-soft, #ffdad6); color: var(--app-danger, #ba1a1a); border-radius: 4px; font-size: 13px;';
        errorEl.textContent = message;

        const container = document.getElementById('messages-container');
        container.parentNode.insertBefore(errorEl, container);

        // Auto-remove after 5 seconds
        setTimeout(() => { if (errorEl.parentNode) errorEl.remove(); }, 5000);
    }

    // Warn if Echo is not connected (real-time won't work)
    if (window.Echo && !window.Echo.socketId()) {
        console.warn('[Chat] Echo is not connected. Real-time updates may not work.');
    }

    // Initial scroll to bottom
    scrollToBottom();
});
</script>
@endsection