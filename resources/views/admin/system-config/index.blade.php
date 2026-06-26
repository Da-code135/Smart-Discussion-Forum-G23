<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Configuration</title>
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
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: #0056b3;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">Studdit Admin</div>
    </nav>

    <div class="container">
        <div class="card">
            <h2>System Configuration</h2>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.system-config.update') }}">
                @csrf
                @method('PUT')

                <!-- Max Login Attempts -->
                <div class="form-group">
                    <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                    <input
                        type="number"
                        id="max_login_attempts"
                        name="max_login_attempts"
                        value="{{ $configs->firstWhere('config_key', 'max_login_attempts')->config_value ?? 5 }}"
                        min="1"
                        class="form-control"
                        required
                    >
                    <small class="form-text">Number of failed login attempts before lockout</small>
                </div>

                <!-- Lockout Minutes -->
                <div class="form-group">
                    <label for="lockout_minutes" class="form-label">Lockout Duration (minutes)</label>
                    <input
                        type="number"
                        id="lockout_minutes"
                        name="lockout_minutes"
                        value="{{ $configs->firstWhere('config_key', 'lockout_minutes')->config_value ?? 15 }}"
                        min="1"
                        class="form-control"
                        required
                    >
                    <small class="form-text">How long to lock out user after max attempts</small>
                </div>

                <!-- Inactivity Warning Days -->
                <div class="form-group">
                    <label for="inactivity_warning_days" class="form-label">Inactivity Warning (days)</label>
                    <input
                        type="number"
                        id="inactivity_warning_days"
                        name="inactivity_warning_days"
                        value="{{ $configs->firstWhere('config_key', 'inactivity_warning_days')->config_value ?? 30 }}"
                        min="1"
                        class="form-control"
                        required
                    >
                    <small class="form-text">Days of inactivity before first warning</small>
                </div>

                <!-- Blacklist Duration -->
                <div class="form-group">
                    <label for="blacklist_duration_days" class="form-label">Blacklist Duration (days)</label>
                    <input
                        type="number"
                        id="blacklist_duration_days"
                        name="blacklist_duration_days"
                        value="{{ $configs->firstWhere('config_key', 'blacklist_duration_days')->config_value ?? 30 }}"
                        min="1"
                        class="form-control"
                        required
                    >
                    <small class="form-text">How long a user stays blacklisted</small>
                </div>

                <button type="submit" class="btn btn-primary">Save Configuration</button>
            </form>
        </div>
    </div>
</body>
</html>