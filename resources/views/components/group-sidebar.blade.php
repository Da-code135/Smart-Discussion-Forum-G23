@props(['group', 'topicCount' => 0, 'showCreateButton' => true])

<section class="sidebar-card">
    <div class="sidebar-card__header">
        <span class="material-symbols-outlined">folder</span>
        <div>
            <h2>{{ $group->group_name ?? 'General' }}</h2>
            <p style="font-size: 12px; color: var(--app-text-muted); font-weight: 400;">Academic discussion group</p>
        </div>
    </div>

    <p style="font-size: 13px; margin-top: 8px; line-height: 1.5;">
        {{ $group->description ?? 'This academic group uses Studdit for calm, structured discussion.' }}
    </p>

    <div class="group-stats">
        <div class="group-stat">
            <span class="group-stat__value">{{ $topicCount }}</span>
            <span class="group-stat__label">Topics</span>
        </div>
        <div class="group-stat">
            <span class="group-stat__value">•••</span>
            <span class="group-stat__label">Members</span>
        </div>
        <div class="group-stat">
            <span class="group-stat__value">✓</span>
            <span class="group-stat__label">Active</span>
        </div>
    </div>

    @if ($showCreateButton)
        <a href="{{ route('forum.create') }}" class="btn btn-primary btn-block" style="margin-top: 12px;">
            <span class="material-symbols-outlined">add</span>
            New Topic
        </a>
    @endif

    <a href="{{ route('forum.index') }}" class="btn btn-ghost btn-block" style="margin-top: 6px;">
        <span class="material-symbols-outlined">forum</span>
        Browse Forum
    </a>
</section>

<section class="sidebar-card">
    <div class="sidebar-card__header">
        <span class="material-symbols-outlined">list_alt</span>
        <h2>Group Rules</h2>
    </div>
    <ol class="sidebar-rules">
        <li>Keep replies constructive, relevant, and academically respectful.</li>
        <li>No spam, self-promotion, or off-topic content.</li>
        <li>Respect all members and their perspectives.</li>
        <li>Follow academic integrity guidelines.</li>
    </ol>
</section>
