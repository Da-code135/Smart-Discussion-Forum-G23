<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
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

        .form-text {
            display: block;
            color: #666;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .nav-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }

        .nav-buttons a {
            flex: 1;
            padding: 0.75rem;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .nav-buttons a:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">Studdit</div>
        <span>{{ Auth::user()->full_name }}</span>
    </nav>

    <div class="container">
        <div class="card">
            <h2>Edit Profile</h2>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="{{ old('full_name', Auth::user()->full_name) }}"
                        class="form-control"
                        required
                    >
                    @error('full_name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', Auth::user()->email) }}"
                        class="form-control"
                        required
                    >
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    <small class="form-text">Changing your email requires re-verification.</small>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>

            <div class="nav-buttons">
                <a href="{{ route('profile.picture') }}">Change Picture</a>
                <a href="{{ route('dashboard') }}">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>