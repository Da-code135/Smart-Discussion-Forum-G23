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
            <a href="{{ route('notifications') }}" class="app-topbar-icon-btn" aria-label="Notifications">
                <span class="material-symbols-outlined">notifications</span>
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
