@php
    $activeNav = trim($__env->yieldContent('activeNav'));
    $user = Auth::user();
    $words = preg_split('/\s+/', trim($user->full_name));
    $initials = collect($words)->filter()->map(fn ($word) => strtoupper(substr($word, 0, 1)))->take(2)->join('');
    $avatarTone = ['var(--avatar-tone-1)', 'var(--avatar-tone-2)', 'var(--avatar-tone-3)', 'var(--avatar-tone-4)', 'var(--avatar-tone-5)'][$user->id % 5];
    $groupName = $user->group->group_name ?? 'General Group';
@endphp

<header class="app-topbar">
    <div class="app-topbar-inner">
        <div class="app-brand">
            <div class="app-brand__meta">
                <a href="{{ route('dashboard') }}" class="app-brand__title">Studdit</a>
                <span class="app-brand__group">{{ $groupName }}</span>
            </div>
        </div>

        <nav class="app-topbar-nav" aria-label="Primary navigation">
            <a href="{{ route('dashboard') }}" class="app-topbar-link {{ $activeNav === 'home' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">home</span>
                <span>Home</span>
            </a>
            <a href="{{ route('forum.index') }}" class="app-topbar-link {{ $activeNav === 'topics' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">forum</span>
                <span>Forum</span>
            </a>
            @php
                $quizzesRoute = ($user->isAdmin() || $user->role?->role_name === 'Lecturer')
                    ? route('quizzes.index')
                    : route('quizzes.my-quizzes');
            @endphp
            <a href="{{ $quizzesRoute }}" class="app-topbar-link {{ str_starts_with($activeNav, 'quiz') ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">quiz</span>
                <span>Quizzes</span>
            </a>
            <a href="{{ route('profile.edit') }}" class="app-topbar-link {{ $activeNav === 'profile' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">person</span>
                <span>Profile</span>
            </a>
            <a href="{{ route('password.change') }}" class="app-topbar-link {{ $activeNav === 'settings' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">settings</span>
                <span>Settings</span>
            </a>
            @if ($user->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="app-topbar-link {{ str_starts_with($activeNav, 'admin') ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined">admin_panel_settings</span>
                    <span>Admin</span>
                </a>
            @endif
        </nav>

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
                        <a href="{{ route('profile.edit') }}" class="user-menu__link">
                            <span class="material-symbols-outlined">person</span>
                            <span>My profile</span>
                        </a>
                        <a href="{{ route('forum.index') }}" class="user-menu__link">
                            <span class="material-symbols-outlined">forum</span>
                            <span>Discussion forum</span>
                        </a>
                        @if ($user->isAdmin())
                            <a href="{{ route('admin.users.index') }}" class="user-menu__link">
                                <span class="material-symbols-outlined">manage_accounts</span>
                                <span>User management</span>
                            </a>
                            <a href="{{ route('admin.groups.index') }}" class="user-menu__link">
                                <span class="material-symbols-outlined">group_work</span>
                                <span>Group management</span>
                            </a>
                            <a href="{{ route('admin.moderation.index') }}" class="user-menu__link">
                                <span class="material-symbols-outlined">shield</span>
                                <span>Moderation</span>
                            </a>
                            <a href="{{ route('admin.warnings.index') }}" class="user-menu__link">
                                <span class="material-symbols-outlined">warning</span>
                                <span>Warnings</span>
                            </a>
                            <a href="{{ route('admin.blacklist.index') }}" class="user-menu__link">
                                <span class="material-symbols-outlined">block</span>
                                <span>Blacklist</span>
                            </a>
                            @if ($user->isSystemAdmin())
                                <a href="{{ route('admin.audit-logs.index') }}" class="user-menu__link">
                                    <span class="material-symbols-outlined">receipt_long</span>
                                    <span>Audit logs</span>
                                </a>
                                <a href="{{ route('admin.ip-whitelist.index') }}" class="user-menu__link">
                                    <span class="material-symbols-outlined">security</span>
                                    <span>IP whitelist</span>
                                </a>
                                <a href="{{ route('admin.system-config.index') }}" class="user-menu__link">
                                    <span class="material-symbols-outlined">settings_applications</span>
                                    <span>System config</span>
                                </a>
                                <a href="{{ route('admin.group-statistics.index') }}" class="user-menu__link">
                                    <span class="material-symbols-outlined">insights</span>
                                    <span>Group statistics</span>
                                </a>
                            @endif
                            <a href="{{ route('admin.statistics.index') }}" class="user-menu__link">
                                <span class="material-symbols-outlined">bar_chart</span>
                                <span>Statistics</span>
                            </a>
                        @endif
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
