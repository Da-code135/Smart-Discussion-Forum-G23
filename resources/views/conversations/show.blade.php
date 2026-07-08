@extends('layouts.app')

@section('title', $conversation->name ?? 'Conversation')
@section('activeNav', 'conversations')

@section('content')
<div class="page-shell">
    <div class="page-shell__main page-stack">
        <header class="page-header">
            <div class="page-header-row">
                <div>
                    <a href="{{ route('conversations.index') }}" class="btn btn-sm btn-secondary" style="margin-bottom: 8px;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">arrow_back</span>
                        Back to conversations
                    </a>
                    <h1>
                        @if ($conversation->type === 'group')
                            {{ $conversation->name ?: 'Group Conversation' }}
                        @else
                            @php
                                $other = $conversation->participants->firstWhere('id', '!=', auth()->id());
                            @endphp
                            {{ $other->full_name ?? 'Conversation' }}
                        @endif
                    </h1>
                    <p>
                        {{ $conversation->type === 'group' ? 'Group conversation' : 'Direct conversation' }}
                        &middot;
                        {{ $conversation->participants->count() }} {{ Str::plural('participant', $conversation->participants->count()) }}
                        &middot;
                        Created {{ $conversation->created_at->diffForHumans() }}
                    </p>
                </div>
            </div>
        </header>

        {{-- Participants --}}
        <section class="sidebar-card page-stack">
            <h2>Participants</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                @foreach ($conversation->participants as $participant)
                    @php
                        $words = preg_split('/\s+/', trim($participant->full_name));
                        $initials = collect($words)->filter()->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                        $avatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][$participant->id % 5];
                        $isAdmin = $participant->pivot->role === 'admin';
                    @endphp
                    <div style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: var(--surface); border-radius: 8px;">
                        <span class="app-topbar-avatar" style="--avatar-bg: {{ $avatarTone }}; width: 32px; height: 32px; font-size: 0.75rem;">{{ $initials }}</span>
                        <div>
                            <span style="font-weight: 500;">{{ $participant->full_name }}</span>
                            @if ($participant->id === auth()->id())
                                <span class="badge badge-secondary" style="font-size: 0.7rem;">You</span>
                            @endif
                            @if ($isAdmin)
                                <span class="badge badge-success" style="font-size: 0.7rem;">Admin</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Messages area (placeholder for Person 3) --}}
        <section class="card" style="min-height: 300px; display: flex; align-items: center; justify-content: center;">
            <div class="empty-state">
                <span class="material-symbols-outlined" style="font-size: 40px;">chat</span>
                <h2>Messaging coming soon</h2>
                <p>Send and receive messages in this conversation here.</p>
            </div>
        </section>
    </div>

    <aside class="page-shell__sidebar page-stack">
        @if ($conversation->type === 'group')
            <section class="sidebar-card page-stack">
                <h2>Manage conversation</h2>
                <p style="color: var(--text-muted); font-size: 0.875rem;">Add or remove participants</p>
            </section>
        @endif

        <section class="sidebar-card page-stack">
            <h2>Details</h2>
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                <div>Type: {{ ucfirst($conversation->type) }}</div>
                <div>Created: {{ $conversation->created_at->format('M j, Y') }}</div>
                @if ($conversation->last_activity_at)
                    <div>Last activity: {{ $conversation->last_activity_at->diffForHumans() }}</div>
                @endif
            </div>
        </section>
    </aside>
</div>
@endsection
