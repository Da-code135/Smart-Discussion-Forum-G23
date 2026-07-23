@php
    $activeNav = trim($__env->yieldContent('activeNav'));
    $user = Auth::user();
    $quizzesRoute = ($user->isAdmin() || $user->role?->role_name === 'Lecturer')
        ? route('quizzes.index')
        : route('quizzes.my-quizzes');
@endphp

<aside class="app-sidebar" data-sidebar>
    <div class="sidebar-inner">
        <nav class="sidebar-nav">
            {{-- Main nav section --}}
            <div class="sidebar-nav-section">
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ $activeNav === 'home' ? 'is-active' : '' }}" title="Home">
                    <span class="material-symbols-outlined sidebar-icon">home</span>
                    <span class="sidebar-label">Home</span>
                </a>
                <a href="{{ route('forum.index') }}" class="sidebar-link {{ $activeNav === 'topics' ? 'is-active' : '' }}" title="Forum">
                    <span class="material-symbols-outlined sidebar-icon">forum</span>
                    <span class="sidebar-label">Forum</span>
                </a>
                <a href="{{ $quizzesRoute }}" class="sidebar-link {{ str_starts_with($activeNav, 'quiz') ? 'is-active' : '' }}" title="Quizzes">
                    <span class="material-symbols-outlined sidebar-icon">quiz</span>
                    <span class="sidebar-label">Quizzes</span>
                </a>
                <a href="{{ route('conversations.index') }}" class="sidebar-link {{ $activeNav === 'conversations' ? 'is-active' : '' }}" title="Messages" style="position: relative;">
                    <span class="material-symbols-outlined sidebar-icon">chat</span>
                    <span class="sidebar-label">Messages</span>
                    @if (!empty($unreadMessageCount) && $unreadMessageCount > 0)
                        <span class="sidebar-badge">{{ min($unreadMessageCount, 99) }}</span>
                    @endif
                </a>
            </div>

            {{-- Profile section --}}
            <div class="sidebar-nav-section">
                <a href="{{ route('profile.edit') }}" class="sidebar-link {{ $activeNav === 'profile' ? 'is-active' : '' }}" title="Profile">
                    <span class="material-symbols-outlined sidebar-icon">person</span>
                    <span class="sidebar-label">Profile</span>
                </a>
                <a href="{{ route('password.change') }}" class="sidebar-link {{ $activeNav === 'settings' ? 'is-active' : '' }}" title="Settings">
                    <span class="material-symbols-outlined sidebar-icon">settings</span>
                    <span class="sidebar-label">Settings</span>
                </a>
            </div>

            {{-- Admin section --}}
            @if ($user->isAdmin())
                <div class="sidebar-nav-section">
                    <div class="sidebar-section-title">
                        <span class="material-symbols-outlined sidebar-icon">admin_panel_settings</span>
                        <span class="sidebar-label">Admin</span>
                    </div>
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ str_starts_with($activeNav, 'admin-dashboard') ? 'is-active' : '' }}" title="Admin Dashboard">
                        <span class="material-symbols-outlined sidebar-icon">dashboard</span>
                        <span class="sidebar-label">Dashboard</span>
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ $activeNav === 'admin-users' ? 'is-active' : '' }}" title="Users">
                        <span class="material-symbols-outlined sidebar-icon">manage_accounts</span>
                        <span class="sidebar-label">Users</span>
                    </a>
                    <a href="{{ route('admin.groups.index') }}" class="sidebar-link" title="Groups">
                        <span class="material-symbols-outlined sidebar-icon">group_work</span>
                        <span class="sidebar-label">Groups</span>
                    </a>
                    <a href="{{ route('admin.moderation.index') }}" class="sidebar-link {{ $activeNav === 'admin-moderation' ? 'is-active' : '' }}" title="Moderation">
                        <span class="material-symbols-outlined sidebar-icon">shield</span>
                        <span class="sidebar-label">Moderation</span>
                    </a>
                    <a href="{{ route('admin.warnings.index') }}" class="sidebar-link" title="Warnings">
                        <span class="material-symbols-outlined sidebar-icon">warning</span>
                        <span class="sidebar-label">Warnings</span>
                    </a>
                    <a href="{{ route('admin.blacklist.index') }}" class="sidebar-link" title="Blacklist">
                        <span class="material-symbols-outlined sidebar-icon">block</span>
                        <span class="sidebar-label">Blacklist</span>
                    </a>
                    <a href="{{ route('admin.audit-logs.index') }}" class="sidebar-link" title="Audit Logs">
                        <span class="material-symbols-outlined sidebar-icon">receipt_long</span>
                        <span class="sidebar-label">Audit Logs</span>
                    </a>
                    @if ($user->isSystemAdmin())
                        <a href="{{ route('admin.ip-whitelist.index') }}" class="sidebar-link" title="IP Whitelist">
                            <span class="material-symbols-outlined sidebar-icon">security</span>
                            <span class="sidebar-label">IP Whitelist</span>
                        </a>
                        <a href="{{ route('admin.system-config.index') }}" class="sidebar-link" title="System Config">
                            <span class="material-symbols-outlined sidebar-icon">settings_applications</span>
                            <span class="sidebar-label">System Config</span>
                        </a>
                    @endif
                    <a href="{{ route('admin.group-statistics.index') }}" class="sidebar-link" title="Group Stats">
                        <span class="material-symbols-outlined sidebar-icon">insights</span>
                        <span class="sidebar-label">Group Stats</span>
                    </a>
                    <a href="{{ route('admin.statistics.index') }}" class="sidebar-link" title="Statistics">
                        <span class="material-symbols-outlined sidebar-icon">bar_chart</span>
                        <span class="sidebar-label">Statistics</span>
                    </a>
                </div>
            @endif
        </nav>

        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-link sidebar-logout" title="Logout">
                    <span class="material-symbols-outlined sidebar-icon">logout</span>
                    <span class="sidebar-label">Logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>
