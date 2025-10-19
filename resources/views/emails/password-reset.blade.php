<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - SchoolSavvy</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #3B82F6;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
        }
        .content {
            padding: 40px 30px;
        }
        .content h2 {
            color: #333;
            margin-top: 0;
        }
        .button {
            display: inline-block;
            padding: 14px 35px;
            background-color: #3B82F6;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
            text-align: center;
        }
        .button:hover {
            background-color: #2563EB;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #f9fafb;
            font-size: 13px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .warning {
            background-color: #FEF2F2;
            border-left: 4px solid #EF4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning strong {
            color: #DC2626;
        }
        .warning ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        .warning li {
            margin: 5px 0;
        }
        .token-box {
            background-color: #F3F4F6;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin: 15px 0;
            border: 1px solid #E5E7EB;
            font-size: 14px;
        }
        .info-box {
            background-color: #EFF6FF;
            border-left: 4px solid #3B82F6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .divider {
            height: 1px;
            background-color: #E5E7EB;
            margin: 30px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>🎓 SchoolSavvy</h1>
            <p>Password Reset Request</p>
        </div>
        
        <div class="content">
            <h2>Hello {{ $userName }},</h2>
            
            <p>We received a request to reset the password for your <strong>{{ $userType }}</strong> account.</p>
            
            <p>Click the button below to reset your password:</p>
            
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="button">Reset Password</a>
            </div>
            
            <div class="divider"></div>
            
            <p><strong>Alternative Method:</strong></p>
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <div class="token-box">{{ $resetUrl }}</div>
            
            <div class="warning">
                <strong>⚠️ Security Notice:</strong>
                <ul>
                    <li>This link will expire in <strong>{{ $expiresIn }}</strong></li>
                    <li>If you didn't request this password reset, please ignore this email and your password will remain unchanged</li>
                    <li>Never share this link with anyone for security reasons</li>
                </ul>
            </div>
            
            <div class="info-box">
                <p><strong>📱 For Mobile App Users:</strong></p>
                <p>You can also use this reset token directly in the app:</p>
                <div class="token-box">{{ $token }}</div>
            </div>
            
            <div class="divider"></div>
            
            <p>If you have any questions or concerns, please contact our support team.</p>
            
            <p style="margin-top: 30px;">Best regards,<br>
            <strong>The SchoolSavvy Team</strong></p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} SchoolSavvy. All rights reserved.</p>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p style="margin-top: 10px; font-size: 12px;">
                If you're having trouble with the button, copy and paste the URL into your web browser.
            </p>
        </div>
    </div>
</body>
</html>
