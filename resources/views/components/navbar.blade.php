<nav class="navbar">
    <div class="navbar-brand">{{ config('app.name') }}</div>
    <div class="navbar-menu">
        @auth
            <span class="nav-link">{{ Auth::user()->full_name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="logout-form">
                @csrf
                <button type="submit" class="btn btn-danger btn-sm">Logout</button>
            </form>
        @endauth
    </div>
</nav>
