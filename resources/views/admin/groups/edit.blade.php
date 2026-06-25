<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Group</title>
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
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }

        .container {
            max-width: 600px;
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
            margin-top: 0;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
        }

        .form-error {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .form-text {
            display: block;
            color: #666;
            font-size: 0.875rem;
            margin-top: 0.25rem;
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

        .btn-danger {
            background: #dc3545;
            color: white;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .danger-zone {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #dc3545;
        }

        .danger-zone h3 {
            color: #dc3545;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">Studdit Admin</div>
    </nav>

    <div class="container">
        <div class="card">
            <h2>Edit Group: {{ $group->group_name }}</h2>

            <form method="POST" action="{{ route('admin.groups.update', $group) }}">
                @csrf
                @method('PUT')

                <!-- GROUP NAME -->
                <div class="form-group">
                    <label for="group_name" class="form-label">Group Name *</label>
                    <input
                        type="text"
                        id="group_name"
                        name="group_name"
                        value="{{ old('group_name', $group->group_name) }}"
                        class="form-control"
                        required
                        maxlength="100"
                    >
                    @error('group_name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <!-- DESCRIPTION -->
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        rows="4"
                        maxlength="500"
                    >{{ old('description', $group->description) }}</textarea>
                    @error('description')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <!-- BUTTONS -->
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary">Cancel</a>

                <!-- DANGER ZONE -->
                @if ($group->group_name !== 'General')
                    <div class="danger-zone">
                        <h3>Danger Zone</h3>
                        <p>Once you delete a group, there is no going back. Please be certain.</p>

                        <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" style="margin-top: 1rem;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This action cannot be undone.')">
                                Delete This Group
                            </button>
                        </form>
                    </div>
                @endif
            </form>
        </div>
    </div>
</body>
</html>