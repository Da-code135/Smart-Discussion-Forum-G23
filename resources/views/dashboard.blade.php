<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Studdit</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }

        .navbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }

        .navbar-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: #333;
            font-weight: 500;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .dashboard-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .dashboard-content h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .user-role {
            color: #666;
            margin-bottom: 2rem;
        }

        .role-section {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f9f9f9;
            border-left: 4px solid #007bff;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .action-buttons a {
            padding: 0.75rem 1.5rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .action-buttons a:hover {
            background: #0056b3;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .stat-card {
            background: #f0f0f0;
            padding: 1rem;
            border-radius: 4px;
            text-align: center;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: bold;
            color: #333;
        }

        .status-active {
            color: #28a745;
        }

        .status-warned {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">Studdit</div>
        <div class="navbar-menu">
            <span class="nav-link">{{ Auth::user()->full_name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-danger">Logout</button>
            </form>
        </div>
    </nav>

    <div class="dashboard-content">
        <h1>Welcome, {{ Auth::user()->full_name }}!</h1>
        <p class="user-role">Role: <strong>{{ Auth::user()->role->role_name }}</strong></p>

        <!-- #79: ROLE-BASED RENDERING -->
        @if (Auth::user()->role->role_name === 'Administrator')
            <div class="role-section">
                <h2>Administrator Dashboard</h2>
                <p>You have access to moderation and user management tools.</p>

                <div class="action-buttons">
                    <a href="{{ route('admin.users-index') }}">User Management</a>
                    <a href="{{ route('admin.statistics') }}">View Statistics</a>
                </div>
            </div>
        @else
            <div class="role-section">
                <h2>Forum Dashboard</h2>
                <p>Welcome to Studdit! Start exploring or create a new topic.</p>

                <div class="action-buttons">
                    <a href="{{ route('forum.index') }}">Enter Forum</a>
                    <a href="{{ route('profile.edit') }}">View Profile</a>
                </div>
            </div>
        @endif

        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-label">Account Status</div>
                <div class="stat-value {{ Auth::user()->account_status === 'active' ? 'status-active' : 'status-warned' }}">
                    {{ ucfirst(Auth::user()->account_status) }}
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Last Active</div>
                <div class="stat-value">
                    {{ Auth::user()->last_active_at ? Auth::user()->last_active_at->format('M d, Y H:i') : 'Never' }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>