<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 500px;
            padding: 1rem;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .card h2 {
            color: #333;
            margin-bottom: 1rem;
        }

        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .card p {
            color: #666;
            margin-bottom: 1rem;
            line-height: 1.6;
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

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .form-group {
            margin-top: 2rem;
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

        .divider {
            margin: 2rem 0;
            text-align: center;
            color: #999;
        }

        .nav-link {
            color: #007bff;
            text-decoration: none;
        }

        .nav-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon">📧</div>

            <h2>Verify Your Email</h2>

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

            @if (Auth::check())
                <p>We've sent a verification email to:</p>
                <p style="font-weight: 600; color: #007bff;">{{ Auth::user()->email }}</p>

                <p>Please click the link in the email to verify your address. The link will expire in 24 hours.</p>

                <div class="alert alert-info">
                    💡 <strong>Didn't receive the email?</strong> Check your spam folder or request a new verification email below.
                </div>

                <!-- #153: RESEND BUTTON -->
                <form method="POST" action="{{ route('verify-email.resend') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        Resend Verification Email
                    </button>
                </form>

                <div class="divider">or</div>

                <!-- CONTINUE TO DASHBOARD -->
                <p>Already verified?</p>
                <a href="{{ route('dashboard') }}" class="nav-link">Go to Dashboard</a>
            @else
                <p>Please log in to verify your email address.</p>
                <a href="{{ route('login') }}" class="btn btn-primary">Go to Login</a>
            @endif
        </div>
    </div>
</body>
</html>