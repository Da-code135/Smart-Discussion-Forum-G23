<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .dashboard-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .dashboard-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .dashboard-card h3 {
            color: #666;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        .dashboard-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 1rem;
        }

        .dashboard-card a {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .dashboard-card a:hover {
            background: #0056b3;
        }

        .admin-links {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-links h2 {
            color: #333;
            margin-bottom: 1.5rem;
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .link-btn {
            padding: 1rem;
            background: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .link-btn:hover {
            border-color: #007bff;
            color: #007bff;
            background: #f0f7ff;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">Studdit Admin</div>
        <div class="navbar-menu">
            <span>{{ Auth::user()->full_name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                @csrf
                <button type="submit" class="btn-danger">Logout</button>
            </form>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h1>Welcome, Administrator</h1>
            <p>Manage your Studdit platform from here</p>
        </div>

        <!-- QUICK STATS -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Total Users</h3>
                <div class="number">{{ \App\Models\User::count() }}</div>
                <a href="{{ route('admin.users.index') }}">View Users</a>
            </div>

            <div class="dashboard-card">
                <h3>Active Users</h3>
                <div class="number">{{ \App\Models\User::where('account_status', 'active')->count() }}</div>
            </div>

            <div class="dashboard-card">
                <h3>Warned Users</h3>
                <div class="number">{{ \App\Models\User::where('account_status', 'warned')->count() }}</div>
            </div>

            <div class="dashboard-card">
                <h3>Blacklisted Users</h3>
                <div class="number">{{ \App\Models\User::where('account_status', 'blacklisted')->count() }}</div>
            </div>
        </div>

        <!-- ADMIN LINKS -->
        <div class="admin-links">
            <h2>Management Tools</h2>
            <div class="links-grid">
                <a href="{{ route('admin.users.index') }}" class="link-btn">
                    👥 User Management
                </a>
                <a href="{{ route('admin.system-config.index') }}" class="link-btn">
                    ⚙️ System Configuration
                </a>
                <a href="{{ route('dashboard') }}" class="link-btn">
                    🏠 Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>