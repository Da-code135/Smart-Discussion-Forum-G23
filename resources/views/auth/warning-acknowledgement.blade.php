<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Warning</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #fff3cd;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .warning-container {
            width: 100%;
            max-width: 500px;
            padding: 1rem;
        }

        .warning-card {
            background: white;
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-left: 4px solid #ffc107;
            text-align: center;
        }

        .warning-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .warning-title {
            color: #856404;
            margin-bottom: 1.5rem;
            font-size: 1.75rem;
        }

        .warning-message {
            text-align: left;
            margin-bottom: 2rem;
        }

        .warning-message p {
            color: #333;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .warning-details {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 4px;
            margin: 1.5rem 0;
            text-align: left;
        }

        .warning-details h3 {
            color: #333;
            margin-top: 0;
        }

        .warning-details ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .warning-details li {
            color: #555;
            margin-bottom: 0.5rem;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 4px;
            font-size: 1.1rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #ffc107;
            color: #333;
        }

        .btn-primary:hover {
            background: #ffb300;
        }
    </style>
</head>
<body>
    <div class="warning-container">
        <div class="warning-card">
            <div class="warning-icon">⚠️</div>

            <h2 class="warning-title">Account Warning</h2>

            <div class="warning-message">
                <p>Your account has received a warning due to inactivity or violation of platform rules.</p>

                <p><strong>Please acknowledge this warning to continue using Studdit.</strong></p>

                <div class="warning-details">
                    <h3>What this means:</h3>
                    <ul>
                        <li>Your account remains active</li>
                        <li>You can continue using the platform</li>
                        <li>A second warning will result in automatic blacklisting</li>
                        <li>Review the platform rules and participate responsibly</li>
                    </ul>
                </div>
            </div>

            <form method="POST" action="{{ route('warning-acknowledgement.acknowledge') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    I Understand and Acknowledge
                </button>
            </form>
        </div>
    </div>
</body>
</html>