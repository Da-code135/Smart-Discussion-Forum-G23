<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Group</title>
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

        .btn-link {
            color: #007bff;
            text-decoration: none;
        }

        .btn-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">Studdit Admin</div>
    </nav>

    <div class="container">
        <div class="card">
            <h2>Create New Group</h2>

            <form method="POST" action="{{ route('admin.groups.store') }}">
                @csrf

                <!-- GROUP NAME -->
                <div class="form-group">
                    <label for="group_name" class="form-label">Group Name *</label>
                    <input
                        type="text"
                        id="group_name"
                        name="group_name"
                        value="{{ old('group_name') }}"
                        placeholder="e.g., Computer Science 101"
                        class="form-control"
                        required
                        maxlength="100"
                    >
                    @error('group_name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Maximum 100 characters</small>
                </div>

                <!-- DESCRIPTION -->
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        placeholder="Optional description for this group..."
                        class="form-control"
                        rows="4"
                        maxlength="500"
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Maximum 500 characters</small>
                </div>

                <!-- BUTTONS -->
                <button type="submit" class="btn btn-primary">Create Group</button>
                <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>