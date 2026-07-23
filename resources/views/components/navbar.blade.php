@php
    $activeNav = trim($__env->yieldContent('activeNav'));
    $user = Auth::user();
    $words = preg_split('/\s+/', trim($user->full_name));
    $initials = collect($words)->filter()->map(fn ($word) => strtoupper(substr($word, 0, 1)))->take(2)->join('');
    $avatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][$user->id % 5];
    $groupName = $user->isSystemAdmin() ? 'System Administrator' : ($user->group?->group_name ?? 'General Group');
@endphp

<header class="app-topbar">
    <div class="app-topbar-inner">
        <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar">
            <span class="material-symbols-outlined">menu</span>
        </button>

        <div class="app-brand">
            <div class="app-brand__meta">
                <a href="{{ route('dashboard') }}" class="app-brand__title">Studdit</a>
                <span class="app-brand__group">{{ $groupName }}</span>
            </div>
        </div>

        {{-- Search bar --}}
        <form method="GET" action="{{ route('forum.search') }}" class="topbar-search">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" placeholder="Search Studdit..." aria-label="Search topics">
        </form>

        <div class="app-topbar-actions">
            <a href="{{ route('notifications') }}" class="app-topbar-icon-btn" aria-label="Notifications" style="position: relative;">
                <span class="material-symbols-outlined">notifications</span>
                @php
                    $unreadNotifCount = Auth::user()->notifications()->whereNull('read_at')->count();
                @endphp
                @if ($unreadNotifCount > 0)
                    <span style="position: absolute; top: 2px; right: 2px; background: #f44336; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; line-height: 1;">
                        {{ min($unreadNotifCount, 99) }}
                    </span>
                @endif
            </a>

            <div class="user-menu" data-user-menu>
                <button type="button" class="user-menu__trigger" data-menu-toggle aria-label="Open user menu">
                    <span class="app-topbar-avatar" style="--avatar-bg: {{ $avatarTone }};">{{ $initials }}</span>
                    <span>{{ $user->full_name }}</span>
                    <span class="material-symbols-outlined">expand_more</span>
                </button>

                <div class="user-menu__panel">
                    <div class="user-menu__section">
                        <div style="padding: 8px 12px; display: flex; align-items: center; gap: 10px;">
                            <span class="app-topbar-avatar" style="--avatar-bg: {{ $avatarTone }}; width: 36px; height: 36px; font-size: 12px;">{{ $initials }}</span>
                            <div>
                                <div style="font-weight: 600; font-size: 14px; color: var(--app-text-primary);">{{ $user->full_name }}</div>
                                <div style="font-size: 12px; color: var(--app-text-muted);">{{ $user->role->role_name }}</div>
                            </div>
                        </div>
                        <hr style="border: 0; border-top: 1px solid var(--app-border); margin: 4px 0;">
                        <a href="{{ route('profile.edit') }}" class="user-menu__link">
                            <span class="material-symbols-outlined">person</span>
                            <span>My profile</span>
                        </a>
                        <a href="{{ route('password.change') }}" class="user-menu__link">
                            <span class="material-symbols-outlined">settings</span>
                            <span>Settings</span>
                        </a>
                        <hr style="border: 0; border-top: 1px solid var(--app-border); margin: 4px 0;">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="user-menu__logout">
                                <span class="material-symbols-outlined">logout</span>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
