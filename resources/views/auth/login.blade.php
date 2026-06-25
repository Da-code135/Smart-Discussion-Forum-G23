<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Discussion Forum</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ambient-bg: #EAE7DC;
            --forum-blue: #1E5FA6;
            --card-white: #FFFFFF;
            --input-bg: #F1EFE7;
            --header-green: #59623e;
            --error-bg: #ffdad6;
            --error-border: #ba1a1a;
            --error-text: #93000a;
            --warning-bg: #ffdcc4;
            --warning-border: #7c5639;
            --warning-text: #5c3a1f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: var(--ambient-bg);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .site-header {
            width: 100%;
            padding: 1rem 1.5rem;
            background-color: var(--header-green);
        }

        @media (min-width: 768px) {
            .site-header {
                padding: 1rem 3rem;
            }
        }

        .site-header-inner {
            max-width: 80rem;
            margin: 0 auto;
        }

        .site-title {
            color: #fff;
            font-size: 1.25rem;
            font-weight: 600;
        }

        @media (min-width: 768px) {
            .site-title {
                font-size: 1.5rem;
            }
        }

        .main-content {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 2rem;
            }
        }

        .login-card {
            width: 100%;
            max-width: 28rem;
            background-color: var(--card-white);
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 2rem;
        }

        @media (min-width: 768px) {
            .login-card {
                padding: 3rem;
            }
        }

        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .card-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.25rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: var(--input-bg);
            border: 1px solid #D1D5DB;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .form-input:focus {
            border-color: transparent;
            box-shadow: 0 0 0 2px #60a5fa;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-input {
            padding-right: 2.75rem;
        }

        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password:hover {
            color: #4b5563;
        }

        .toggle-password svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .form-error {
            display: block;
            color: var(--error-text);
            font-size: 0.8125rem;
            margin-top: 0.25rem;
        }

        .alert {
            padding: 0.75rem;
            border-radius: 0.375rem;
            text-align: center;
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .alert-error {
            background-color: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        .alert-warning {
            background-color: var(--warning-bg);
            border: 1px solid var(--warning-border);
            color: var(--warning-text);
        }

        .remember-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .remember-group label {
            margin-bottom: 0;
            font-weight: 400;
            color: #374151;
            cursor: pointer;
        }

        .remember-group input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            accent-color: var(--forum-blue);
            cursor: pointer;
        }

        .btn-primary {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: var(--forum-blue);
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.125rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s;
        }

        .btn-primary:hover {
            background-color: #164E85;
        }

        .btn-primary:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }

        .submit-wrapper {
            padding-top: 0.5rem;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #374151;
            font-size: 1rem;
        }

        .register-link a {
            color: var(--header-green);
            font-weight: 500;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .site-footer {
            padding: 2rem 1rem;
            text-align: center;
        }

        .site-footer p {
            color: #6b7280;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="site-header-inner">
            <h1 class="site-title">Smart Discussion Forum</h1>
        </div>
    </header>

    <main class="main-content">
        <div class="login-card">
            <div class="card-header">
                <h2 class="card-title">Member Login</h2>
                <p class="card-subtitle">Enter your credentials to continue</p>
            </div>

            <form class="login-form" method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="e.g. john@example.com"
                        class="form-input"
                        required
                        autocomplete="email"
                    >
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            class="form-input"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                @if ($errors->has('email') && str_contains(strtolower($errors->first('email')), 'suspend'))
                    <div class="alert alert-warning" role="alert">
                        {{ $errors->first('email') }}
                    </div>
                @elseif ($errors->any())
                    <div class="alert alert-error" role="alert">
                        @if ($errors->has('email') || $errors->has('password'))
                            Invalid email or password.
                        @else
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        @endif
                    </div>
                @endif

                <div class="remember-group">
                    <input
                        type="checkbox"
                        id="remember"
                        name="remember"
                        value="1"
                        {{ old('remember') ? 'checked' : '' }}
                    >
                    <label for="remember">Remember me for 30 days</label>
                </div>

                <div class="submit-wrapper">
                    <button type="submit" class="btn-primary">Login</button>
                </div>

                <p class="register-link">
                    New member? <a href="{{ route('register') }}">Register here</a>
                </p>
            </form>
        </div>
    </main>

    
    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const password = document.getElementById('password');
            const isHidden = password.type === 'password';
            password.type = isHidden ? 'text' : 'password';
            this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    </script>
</body>
</html>