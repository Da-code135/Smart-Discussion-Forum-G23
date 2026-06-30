@php
    $activeNav = trim($__env->yieldContent('activeNav'));
    $user = Auth::user();
    $initials = collect(explode(' ', $user->full_name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
@endphp

<header class="app-topbar">
    <div class="app-topbar-brand">{{ config('app.name') }}</div>
    <div class="app-topbar-actions">
        <a href="{{ route('verify-email') }}" class="app-topbar-icon-btn" aria-label="Notifications">
            <span class="material-symbols-outlined">notifications</span>
        </a>
        <div class="app-topbar-user">
            <div class="app-topbar-avatar">{{ $initials }}</div>
            <span class="app-topbar-name">{{ $user->full_name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="logout-form">
                @csrf
                <button type="submit" class="app-topbar-logout">Logout</button>
            </form>
        </div>
    </div>
</header>

<aside class="app-sidebar" aria-label="Main navigation">
    <div class="app-sidebar-header">
        <p class="app-sidebar-label">MENU</p>
        <p class="app-sidebar-sub">Academic Discourse</p>
    </div>

    <nav class="app-sidebar-nav">
        <a href="{{ route('dashboard') }}" class="app-sidebar-link {{ $activeNav === 'home' ? 'is-active' : '' }}">
            <span class="material-symbols-outlined">home</span>
            <span>Home</span>
        </a>
        <a href="{{ route('forum.index') }}" class="app-sidebar-link {{ $activeNav === 'topics' ? 'is-active' : '' }}">
            <span class="material-symbols-outlined">forum</span>
            <span>My Topics</span>
        </a>
        <a href="{{ route('forum.index') }}" class="app-sidebar-link {{ $activeNav === 'discussions' ? 'is-active' : '' }}">
            <span class="material-symbols-outlined">groups</span>
            <span>All Discussions</span>
        </a>
        <a href="{{ route('forum.index') }}" class="app-sidebar-link {{ $activeNav === 'quizzes' ? 'is-active' : '' }}">
            <span class="material-symbols-outlined">quiz</span>
            <span>Quizzes</span>
        </a>
        <a href="{{ route('verify-email') }}" class="app-sidebar-link {{ $activeNav === 'notifications' ? 'is-active' : '' }}">
            <span class="material-symbols-outlined">notifications</span>
            <span>Notifications</span>
        </a>
        <a href="{{ route('profile.edit') }}" class="app-sidebar-link {{ $activeNav === 'profile' ? 'is-active' : '' }}">
            <span class="material-symbols-outlined">person</span>
            <span>My Profile</span>
        </a>

        {{-- Admin Section - Only visible to admins --}}
        @if ($user->isAdmin())
            <p class="app-sidebar-label" style="margin-top: 1.5rem;">ADMIN</p>

            <a href="{{ route('admin.dashboard') }}" class="app-sidebar-link {{ $activeNav === 'admin-dashboard' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Admin Dashboard</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="app-sidebar-link {{ $activeNav === 'admin-users' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">manage_accounts</span>
                <span>Users</span>
            </a>
            <a href="{{ route('admin.groups.index') }}" class="app-sidebar-link {{ $activeNav === 'admin-groups' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">group_work</span>
                <span>Groups</span>
            </a>
            <a href="{{ route('admin.audit-logs.index') }}" class="app-sidebar-link {{ $activeNav === 'admin-audit' ? 'is-active' : '' }}">
                <span class="material-symbols-outlined">fact_check</span>
                <span>Audit Logs</span>
            </a>

            {{-- System Admin only links --}}
            @if ($user->isSystemAdmin())
                <a href="{{ route('admin.system-config.index') }}" class="app-sidebar-link {{ $activeNav === 'admin-config' ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined">settings</span>
                    <span>System Config</span>
                </a>
                <a href="{{ route('admin.ip-whitelist.index') }}" class="app-sidebar-link {{ $activeNav === 'admin-ip' ? 'is-active' : '' }}">
                    <span class="material-symbols-outlined">security</span>
                    <span>IP Whitelist</span>
                </a>
            @endif
        @endif
    </nav>

    <div class="app-sidebar-footer">
        <a href="{{ route('password.change') }}" class="app-sidebar-link {{ $activeNav === 'settings' ? 'is-active' : '' }}">
            <span class="material-symbols-outlined">settings</span>
            <span>Settings</span>
        </a>
        <a href="{{ route('forum.index') }}" class="app-sidebar-link">
            <span class="material-symbols-outlined">help</span>
            <span>Help</span>
        </a>
        <form method="POST" action="{{ route('logout') }}" class="app-sidebar-logout-form">
            @csrf
            <button type="submit" class="app-sidebar-link app-sidebar-link--danger">
                <span class="material-symbols-outlined">logout</span>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>

<nav class="app-mobile-nav" aria-label="Mobile navigation">
    <a href="{{ route('dashboard') }}" class="app-mobile-nav-link {{ $activeNav === 'home' ? 'is-active' : '' }}">
        <span class="material-symbols-outlined filled">home</span>
        <span>Home</span>
    </a>
    <a href="{{ route('forum.index') }}" class="app-mobile-nav-link">
        <span class="material-symbols-outlined">forum</span>
        <span>Topics</span>
    </a>
    <a href="{{ route('forum.index') }}" class="app-mobile-nav-link">
        <span class="material-symbols-outlined">quiz</span>
        <span>Quiz</span>
    </a>
    <a href="{{ route('profile.edit') }}" class="app-mobile-nav-link {{ $activeNav === 'profile' ? 'is-active' : '' }}">
        <span class="material-symbols-outlined">person</span>
        <span>Profile</span>
    </a>
    @if ($user->isAdmin())
        <a href="{{ route('admin.dashboard') }}" class="app-mobile-nav-link">
            <span class="material-symbols-outlined">dashboard</span>
            <span>Admin</span>
        </a>
    @endif
</nav>
