<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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

        .admin-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-header h1 {
            color: #333;
            margin-bottom: 1rem;
        }

        .filter-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background: #ffb300;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
            border-bottom: 2px solid #ddd;
        }

        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-warned {
            background: #fff3cd;
            color: #856404;
        }

        .status-blacklisted {
            background: #f8d7da;
            color: #721c24;
        }

        .role-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
            background: #e7f3ff;
            color: #004085;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 2rem 1rem;
            background: white;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
        }

        .pagination a:hover {
            background: #007bff;
            color: white;
        }

        .pagination .active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination .disabled {
            color: #ccc;
            border-color: #ddd;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .filter-button-group {
            display: flex;
            gap: 0.5rem;
            grid-column: 1 / -1;
        }

        .role-select {
            position: relative;
            display: inline-block;
        }

        .role-select form {
            display: inline;
        }

        .role-select select {
            padding: 0.4rem 0.6rem;
            font-size: 0.875rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .filter-section {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 0.75rem 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-sm {
                width: 100%;
                text-align: center;
            }
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
        <!-- SUCCESS/ERROR MESSAGES -->
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        <!-- ADMIN HEADER -->
        <div class="admin-header">
            <h1>User Management</h1>
            <p>Manage users, roles, blacklists, and account statuses</p>

            <!-- #89: SEARCH & FILTER SECTION -->
            <form method="GET" action="{{ route('admin.users.index') }}">
                <div class="filter-section">
                    <!-- SEARCH -->
                    <div class="form-group">
                        <label for="search" class="form-label">Search by Name or Email</label>
                        <input
                            type="text"
                            id="search"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search users..."
                            class="form-control"
                        >
                    </div>

                    <!-- FILTER BY ACCOUNT STATUS -->
                    <div class="form-group">
                        <label for="account_status" class="form-label">Filter by Status</label>
                        <select id="account_status" name="account_status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="active" {{ $account_status === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="warned" {{ $account_status === 'warned' ? 'selected' : '' }}>Warned</option>
                            <option value="blacklisted" {{ $account_status === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
                        </select>
                    </div>

                    <!-- FILTER BY ROLE -->
                    <div class="form-group">
                        <label for="role" class="form-label">Filter by Role</label>
                        <select id="role" name="role" class="form-control">
                            <option value="">All Roles</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r->id }}" {{ $role == $r->id ? 'selected' : '' }}>
                                    {{ $r->role_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- FILTER BUTTONS -->
                    <div class="filter-button-group">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- #88: USER TABLE -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <strong>{{ $user->full_name }}</strong>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="role-badge">{{ $user->role->role_name }}</span>
                            </td>
                            <td>
                                <span class="status-badge status-{{ $user->account_status }}">
                                    {{ ucfirst($user->account_status) }}
                                </span>
                            </td>
                            <td>
                                {{ $user->last_active_at ? $user->last_active_at->format('M d, Y H:i') : 'Never' }}
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <!-- #91: CHANGE ROLE -->
                                    <form method="POST" action="{{ route('admin.users.change-role', $user->id) }}" style="display: inline;">
                                        @csrf
                                        <select name="role_id" class="form-control" style="display: inline; width: auto; padding: 0.4rem;" onchange="this.form.submit();">
                                            <option value="">Change Role</option>
                                            @foreach ($roles as $r)
                                                <option value="{{ $r->id }}" {{ $user->role_id === $r->id ? 'selected' : '' }}>
                                                    {{ $r->role_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>

                                    <!-- #90: LIFT BLACKLIST (only show if blacklisted) -->
                                    @if ($user->account_status === 'blacklisted')
                                        <form method="POST" action="{{ route('admin.users.lift-blacklist', $user->id) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Lift blacklist for this user?')">
                                                Lift Blacklist
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                No users found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- #88: PAGINATION -->
        <div class="pagination">
            {{ $users->appends(request()->query())->links() }}
        </div>
    </div>
</body>
</html>