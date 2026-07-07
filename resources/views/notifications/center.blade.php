@php
    /**
     * This partial displays quiz-related notifications (announcements,
     * reminders, and live alerts) to the currently logged-in user.
     *
     * Expected variables (optional):
     *   $limit (int) — how many notifications to show (default 5)
     *
     * Usage in any Blade view:
     *   @include('notifications.center', ['limit' => 5])
     */
    $limit = $limit ?? 5;

    $quizNotifications = auth()->user()->notifications()
        ->whereIn('type', ['quiz_announcement', 'quiz_reminder', 'quiz_live'])
        ->latest()
        ->limit($limit)
        ->get();
@endphp

<div class="card">
    <div class="card-header">
        <h3 style="margin: 0; font-size: 1rem;">Quiz Updates</h3>
    </div>
    <div class="card-body page-stack" style="gap: 0.5rem;">

        @forelse ($quizNotifications as $notif)
            @php
                $data = $notif->data;
                $icon = match ($notif->type) {
                    'quiz_announcement' => '📢',
                    'quiz_reminder'     => '⏰',
                    'quiz_live'         => '🔴',
                    default             => '📌',
                };
                $bgColor = match ($notif->type) {
                    'quiz_announcement' => '#f0f5ff',
                    'quiz_reminder'     => '#fefce8',
                    'quiz_live'         => '#fef2f2',
                    default             => '#f9fafb',
                };
                $borderColor = match ($notif->type) {
                    'quiz_announcement' => '#3b82f6',
                    'quiz_reminder'     => '#eab308',
                    'quiz_live'         => '#dc2626',
                    default             => '#e5e7eb',
                };
            @endphp

            <div style="padding: 0.75rem 1rem; background: {{ $bgColor }}; border-left: 4px solid {{ $borderColor }}; border-radius: 6px;">
                <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                    <span style="font-size: 1.25rem; line-height: 1.4;">{{ $icon }}</span>
                    <div style="flex: 1; min-width: 0;">
                        {{-- Title --}}
                        <p style="margin: 0 0 0.25rem 0; font-weight: 600; font-size: 0.9rem;">
                            @if ($notif->type === 'quiz_announcement')
                                New Quiz: {{ $data['title'] ?? 'Untitled' }}
                            @elseif ($notif->type === 'quiz_reminder')
                                Reminder: {{ $data['title'] ?? 'Untitled' }}
                            @elseif ($notif->type === 'quiz_live')
                                Live Now: {{ $data['title'] ?? 'Untitled' }}
                            @endif
                        </p>

                        {{-- Body --}}
                        <p style="margin: 0 0 0.25rem 0; font-size: 0.8rem; color: #4b5563;">
                            @if ($notif->type === 'quiz_announcement')
                                Scheduled for
                                <strong>{{ $data['scheduled_date'] ?? '?' }}</strong>
                                at
                                <strong>{{ $data['start_time'] ?? '?' }}</strong>
                                ({{ $data['duration_minutes'] ?? '?' }} min)
                            @elseif ($notif->type === 'quiz_reminder')
                                Starts in
                                <strong>{{ $data['minutes_until_start'] ?? '?' }}</strong>
                                minute(s) at {{ $data['start_time'] ?? '?' }}
                                — {{ $data['duration_minutes'] ?? '?' }} min duration
                            @elseif ($notif->type === 'quiz_live')
                                Available now!
                                <strong>{{ $data['duration_minutes'] ?? '?' }}</strong>
                                minutes to complete it.
                            @endif
                        </p>

                        {{-- Meta --}}
                        <p style="margin: 0; font-size: 0.7rem; color: #9ca3af;">
                            {{ $notif->created_at->diffForHumans() }}
                            @if ($data['lecturer_name'] ?? null)
                                &middot; by {{ $data['lecturer_name'] }}
                            @endif
                            @if ($data['quiz_id'] ?? null)
                                &middot;
                                <a href="{{ route('quizzes.announcement', $data['quiz_id']) }}"
                                   style="color: #3b82f6; text-decoration: none;">
                                    View Quiz
                                </a>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

        @empty
            <div style="text-align: center; padding: 2rem 1rem; color: #9ca3af;">
                <p style="margin: 0; font-size: 0.9rem;">No quiz updates yet.</p>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem;">
                    When a quiz is announced, it will appear here.
                </p>
            </div>
        @endforelse

    </div>
</div>
