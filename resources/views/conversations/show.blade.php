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
                    <div class="message-item" data-message-id="{{ $message->id }}">
                        <div class="message-sender">
                            <strong>{{ $message->sender->full_name }}</strong>
                            <small class="message-time">{{ $message->created_at->format('M j, g:i A') }}</small>
                        </div>
                        <div class="message-body">
                            {{ $message->body }}
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

<!-- Laravel Echo JavaScript for real-time messaging -->
<script src="{{ asset('js/app.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Using Laravel Echo (requires npm install laravel-echo pusher-js)
    import Echo from 'laravel-echo';
    
    window.Pusher = require('pusher-js');
    
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT,
        forceTLS: false,
        disableStats: true,
        authorizer: (channel) => {
            return {
                authorize: (socketId, callback) => {
                    // Uses Sanctum token for authorization
                    axios.post('/api/broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channel.name,
                    })
                    .then(response => callback(false, response.data))
                    .catch(error => callback(true, error));
                }
            };
        },
    });
    
    const conversationId = {{ $conversation->id }};
    const userId = {{ auth()->id() }};
    
    // Listen for new messages
    Echo.private(`conversation.${conversationId}`)
        .listen('MessageSent', (e) => {
            appendMessage(e);
        });
    
    // Function to append a new message to the chat view
    function appendMessage(messageData) {
        const messagesList = document.getElementById('messages-list');
        
        const messageElement = document.createElement('div');
        messageElement.className = 'message-item';
        messageElement.dataset.messageId = messageData.id;
        
        messageElement.innerHTML = `
            <div class="message-sender">
                <strong>${messageData.sender_name}</strong>
                <small class="message-time">${formatTime(messageData.created_at)}</small>
            </div>
            <div class="message-body">
                ${escapeHtml(messageData.body)}
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
            month: 'short',
            day: 'numeric',
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
        
        // Submit via AJAX
        fetch(messageForm.getAttribute('action'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                body: messageBody
            })
        })
        .then(response => {
            if (response.ok) {
                // Clear the input
                messageForm.reset();
            } else {
                // Show error
                alert('Failed to send message. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        })
        .finally(() => {
            // Re-enable form
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
    
    // Initial scroll to bottom
    scrollToBottom();
});
</script>
@endsection