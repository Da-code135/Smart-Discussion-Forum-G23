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
            background: #007bff;
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
        .footer {
            text-align: center;
            font-size: 0.875rem;
            color: #666;
            margin-top: 20px;
        }
        .warning {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Studdit!</h1>
        </div>

        <div class="content">
            <p>Hello {{ $user->full_name }},</p>

            <p>Thank you for signing up. Please verify your email address to complete your registration.</p>

            <a href="{{ $verificationUrl }}" class="button">
                Verify Email Address
            </a>

            <p>Or copy and paste this link in your browser:</p>
            <p style="word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 4px;">
                {{ $verificationUrl }}
            </p>

            <div class="warning">
                <strong>⏰ Link expires in {{ $expiryTime }}</strong><br>
                If you didn't create this account, you can safely ignore this email.
            </div>

            <p>Best regards,<br>The Studdit Team</p>
        </div>

        <div class="footer">
            <p>&copy; 2026 Studdit. All rights reserved.</p>
        </div>
    </div>
</body>
</html>