<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Members</title>
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

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #333;
            margin-bottom: 1.5rem;
        }

        .members-list {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .member-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }

        .member-item:last-child {
            border-bottom: none;
        }

        .member-item input[type="checkbox"] {
            margin-right: 1rem;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: #333;
        }

        .member-email {
            font-size: 0.875rem;
            color: #666;
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
            width: 100%;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            width: 100%;
            margin-top: 0.5rem;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-text {
            color: #666;
            font-size: 0.875rem;
            margin-bottom: 1rem;
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

        .select-all-group {
            padding: 1rem;
            background: #f8f9fa;
            border-bottom: 2px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .select-all-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">Studdit Admin</div>
    </nav>

    <div class="container">
        <div class="card">
            <h2>{{ $group->group_name }} — Members</h2>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <p class="form-text">
                {{ $group->users_count }} member(s) currently in this group
            </p>

            <!-- #147: MEMBERSHIP FORM -->
            <form method="POST" action="{{ route('admin.groups.update-members', $group) }}">
                @csrf
                @method('PUT')

                <!-- SELECT ALL CHECKBOX -->
                <div class="select-all-group">
                    <label>
                        <input type="checkbox" id="select-all">
                        <strong>Select All</strong>
                    </label>
                </div>

                <!-- MEMBERS LIST -->
                <div class="members-list">
                    @forelse ($allUsers as $user)
                        <div class="member-item">
                            <input
                                type="checkbox"
                                name="user_ids[]"
                                value="{{ $user->id }}"
                                class="member-checkbox"
                                {{ in_array($user->id, $memberIds) ? 'checked' : '' }}
                            >
                            <div class="member-info">
                                <div class="member-name">{{ $user->full_name }}</div>
                                <div class="member-email">{{ $user->email }}</div>
                            </div>
                        </div>
                    @empty
                        <p style="padding: 1rem; text-align: center;">No users available</p>
                    @endforelse
                </div>

                <!-- BUTTONS -->
                <button type="submit" class="btn btn-primary">Save Members</button>
                <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary">Back</a>
            </form>
        </div>
    </div>

    <script>
        // Select All checkbox functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.member-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    </script>
</body>
</html>