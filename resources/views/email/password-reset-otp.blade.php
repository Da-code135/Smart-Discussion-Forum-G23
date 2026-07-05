<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #1e1b15;
            margin: 0;
            padding: 0;
            background: #fff8f1;
        }
        .container {
            max-width: 560px;
            margin: 40px auto;
            background: #ffffff;
            border: 1px solid #e9e1d8;
            border-radius: 8px;
            overflow: hidden;
        }
        .header {
            background: #59623e;
            color: #ffffff;
            padding: 28px 32px;
        }
        .header h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .body {
            padding: 32px;
        }
        .otp-block {
            margin: 28px 0;
            text-align: center;
            padding: 24px;
            background: #f5f5f5;
            border-radius: 8px;
            letter-spacing: 0.35em;
            font-size: 2.25rem;
            font-weight: 700;
            color: #59623e;
            font-family: 'Courier New', monospace;
        }
        .warning {
            background: #ffdcc4;
            color: #5c3a1f;
            padding: 14px 16px;
            border-radius: 4px;
            font-size: 0.875rem;
            margin: 20px 0 0;
        }
        .footer {
            padding: 20px 32px;
            border-top: 1px solid #e9e1d8;
            font-size: 0.8rem;
            color: #77786d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Studdit — Password Reset</h1>
        </div>

        <div class="body">
            <p>Hello {{ $user->full_name }},</p>

            <p>We received a request to reset your password. Enter the code below in the app to continue.</p>

            <div class="otp-block">{{ $otp }}</div>

            <p style="text-align:center; color:#77786d; font-size:0.875rem;">
                This code expires in <strong>10 minutes</strong> and can only be used once.
            </p>

            <div class="warning">
                <strong>Did not request this?</strong> Ignore this email. Your password will not change
                unless someone enters this code before it expires. If you are concerned, log in and
                change your password immediately.
            </div>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Studdit. This is an automated message — please do not reply.
        </div>
    </div>
</body>
</html>
