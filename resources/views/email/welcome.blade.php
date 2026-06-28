<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #f9f9f9;
        }
        .header {
            background: #28a745;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: white;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-left: 4px solid #007bff;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            font-size: 0.875rem;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Smart Discussion Forum!</h1>
        </div>

        <div class="content">
            <p>Hello {{ $user->full_name }},</p>

            <p>Your account has been successfully created. We're excited to have you as part of our community!</p>

            <div class="info-box">
                <strong>Your Account Details</strong><br>
                Email: {{ $user->email }}<br>
                Role: {{ $user->role->role_name ?? 'Member' }}
            </div>

            <p>Here's what you can do now:</p>
            <ul>
                <li>Join discussion groups and participate in conversations</li>
                <li>Connect with fellow members and share knowledge</li>
                <li>Explore topics and contribute to the community</li>
            </ul>

            <a href="{{ route('login') }}" class="button">
                Go to Dashboard
            </a>

            <p>If you have any questions, feel free to reach out to our support team.</p>

            <p>Best regards,<br>The Smart Discussion Forum Team</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Smart Discussion Forum. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
