<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta-description', 'Studdit academic discussion platform')">
    <title>@yield('title', 'Dashboard') - Studdit</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="app-layout">
    @include('components.navbar')

    <div class="app-body">
        @include('components.sidebar')

        <div class="app-shell">
            <main class="app-main">
                <div class="flash-stack">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            <span class="material-symbols-outlined">check_circle</span>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-error" role="alert">
                            <span class="material-symbols-outlined">error</span>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    @if (session('warning'))
                        <div class="alert alert-warning" role="alert">
                            <span class="material-symbols-outlined">warning</span>
                            <span>{{ session('warning') }}</span>
                        </div>
                    @endif

                    @if (session('info'))
                        <div class="alert alert-info" role="alert">
                            <span class="material-symbols-outlined">info</span>
                            <span>{{ session('info') }}</span>
                        </div>
                    @endif
                </div>

                @yield('content')
            </main>
        </div>
    </div>

    <div class="sidebar-overlay" data-sidebar-overlay onclick="toggleSidebar()"></div>

    @stack('scripts')

    <script>
        function toggleSidebar() {
            document.querySelector('[data-sidebar]').classList.toggle('is-open');
            document.querySelector('[data-sidebar-overlay]').classList.toggle('is-visible');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.querySelector('[data-sidebar-toggle]');
            const sidebar = document.querySelector('[data-sidebar]');
            const overlay = document.querySelector('[data-sidebar-overlay]');

            if (toggle) {
                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (window.innerWidth <= 768) {
                        // Mobile: toggle is-open to slide sidebar in/out
                        sidebar.classList.toggle('is-open');
                        if (overlay) overlay.classList.toggle('is-visible');
                    } else {
                        // Desktop: toggle is-collapsed to shrink/expand
                        sidebar.classList.toggle('is-collapsed');
                        try {
                            localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('is-collapsed') ? '1' : '0');
                        } catch (e) {}
                    }
                });
            }

            // Restore preference
            try {
                const collapsed = localStorage.getItem('sidebar_collapsed');
                if (collapsed === '1') {
                    sidebar.classList.add('is-collapsed');
                }
            } catch (e) {}

            // Mobile: close sidebar when clicking a link
            if (window.innerWidth <= 768) {
                sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
                    link.addEventListener('click', function () {
                        sidebar.classList.remove('is-open');
                        if (overlay) overlay.classList.remove('is-visible');
                    });
                });
            }
        });

        // ========== Share / Social helpers ==========
        function toggleShareMenu(event, menuId) {
            event.stopPropagation();
            const menu = document.getElementById(menuId);
            if (menu) {
                menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
            }
        }

        function copyToClipboard(url) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    alert('Link copied to clipboard!');
                }).catch(function () {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        }

        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('Link copied to clipboard!');
        }

        // Global click handler to close share menus when clicking outside
        document.addEventListener('click', function (event) {
            document.querySelectorAll('.share-menu').forEach(function (menu) {
                if (menu.style.display !== 'none') {
                    const btnId = menu.id.replace('share-menu-', 'share-btn-');
                    const btn = document.getElementById(btnId);
                    if (!event.target.closest('#' + menu.id) && btn && !btn.contains(event.target)) {
                        menu.style.display = 'none';
                    }
                }
            });
        });
    </script>
</body>
</html>
