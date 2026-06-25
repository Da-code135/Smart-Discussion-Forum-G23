<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Picture</title>
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
            max-width: 400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .card h3 {
            color: #333;
            margin-top: 0;
            margin-bottom: 1.5rem;
        }

        .current-picture {
            margin: 1.5rem 0;
        }

        .picture-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
        }

        .placeholder {
            width: 150px;
            height: 150px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 1.5rem auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-error {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .form-text {
            display: block;
            color: #999;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .btn {
            width: 100%;
            padding: 0.75rem;
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

        .nav-buttons {
            margin-top: 1.5rem;
        }

        .nav-buttons a {
            display: block;
            padding: 0.75rem;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
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
    </nav>

    <div class="container">
        <div class="card">
            <h3>Profile Picture</h3>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if (Auth::user()->profile_picture)
                <div class="current-picture">
                    <img
                        src="{{ asset('storage/' . Auth::user()->profile_picture) }}"
                        alt="Profile picture"
                        class="picture-preview"
                    >
                </div>
            @else
                <div class="current-picture">
                    <div class="placeholder">No picture yet</div>
                </div>
            @endif

            <form method="POST" action="{{ route('profile.picture.upload') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <input
                        type="file"
                        name="profile_picture"
                        accept="image/jpeg,image/png"
                        class="form-control"
                        required
                    >
                    @error('profile_picture')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Upload Picture</button>

                <small class="form-text">
                    Supported formats: JPEG, PNG. Maximum size: 2MB
                </small>
            </form>

            <div class="nav-buttons">
                <a href="{{ route('profile.edit') }}">Back to Profile</a>
            </div>
        </div>
    </div>
</body>
</html>