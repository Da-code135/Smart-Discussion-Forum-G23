<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Management</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header h1 {
            color: #333;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
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

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background: #ffb300;
        }

        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 1rem;
            align-items: flex-end;
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

        .member-badge {
            display: inline-block;
            background: #e7f3ff;
            color: #004085;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }

            .admin-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
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

        <!-- HEADER WITH CREATE BUTTON -->
        <div class="admin-header">
            <h1>Group Management</h1>
            <a href="{{ route('admin.groups.create') }}" class="btn btn-primary">
                + Create New Group
            </a>
        </div>

        <!-- #144: SEARCH & SORT -->
        <div class="filter-section">
            <form method="GET" action="{{ route('admin.groups.index') }}" class="filter-form">
                <div class="form-group">
                    <label for="search" class="form-label">Search by Group Name</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search groups..."
                        class="form-control"
                    >
                </div>

                <div class="form-group">
                    <label for="sort_by" class="form-label">Sort By</label>
                    <select id="sort_by" name="sort_by" class="form-control">
                        <option value="created_at" {{ $sort_by === 'created_at' ? 'selected' : '' }}>
                            Newest First
                        </option>
                        <option value="member_count" {{ $sort_by === 'member_count' ? 'selected' : '' }}>
                            Most Members
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <!-- #143: GROUPS TABLE -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Description</th>
                        <th>Members</th>
                        <th>Created By</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr>
                            <td>
                                <strong>{{ $group->group_name }}</strong>
                            </td>
                            <td>
                                {{ $group->description ? Str::limit($group->description, 50) : 'N/A' }}
                            </td>
                            <td>
                                <span class="member-badge">{{ $group->users_count }} members</span>
                            </td>
                            <td>
                                {{ $group->createdBy->full_name ?? 'Unknown' }}
                            </td>
                            <td>
                                {{ $group->created_at->format('M d, Y') }}
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <!-- #147: Manage Members -->
                                    <a href="{{ route('admin.groups.members', $group) }}" class="btn btn-warning btn-sm">
                                        👥 Members
                                    </a>

                                    <!-- #146: Edit Group -->
                                    <a href="{{ route('admin.groups.edit', $group) }}" class="btn btn-primary btn-sm">
                                        Edit
                                    </a>

                                    <!-- #146: Delete Group -->
                                    @if ($group->group_name !== 'General')
                                        <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this group?')">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                No groups found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- #143: PAGINATION -->
        <div class="pagination">
            {{ $groups->appends(request()->query())->links() }}
        </div>
    </div>
</body>
</html>