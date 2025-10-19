<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP - SchoolSavvy</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 0;
            background-color: #f5f5f5;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 20px auto;
        }
        .header {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.95;
        }
        .content {
            padding: 40px 30px;
        }
        .content h2 {
            color: #1f2937;
            margin-top: 0;
            font-size: 24px;
        }
        .content p {
            color: #4b5563;
            margin: 15px 0;
            font-size: 15px;
        }
        .otp-container {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            border: 2px solid #3B82F6;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-label {
            color: #1e40af;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        .otp-code {
            font-size: 48px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .otp-note {
            color: #3b82f6;
            font-size: 13px;
            margin-top: 15px;
        }
        .warning-box {
            background-color: #FEF2F2;
            border-left: 4px solid #EF4444;
            padding: 20px;
            margin: 25px 0;
            border-radius: 6px;
        }
        .warning-box strong {
            color: #DC2626;
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .warning-box ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
            color: #7f1d1d;
        }
        .warning-box li {
            margin: 8px 0;
        }
        .info-box {
            background-color: #F0FDF4;
            border-left: 4px solid #10B981;
            padding: 20px;
            margin: 25px 0;
            border-radius: 6px;
        }
        .info-box strong {
            color: #065F46;
            display: block;
            margin-bottom: 10px;
        }
        .info-box p {
            color: #065F46;
            margin: 5px 0;
        }
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #E5E7EB, transparent);
            margin: 30px 0;
        }
        .footer {
            text-align: center;
            padding: 25px;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            font-size: 13px;
            color: #6b7280;
            margin: 5px 0;
        }
        .footer a {
            color: #3B82F6;
            text-decoration: none;
        }
        .badge {
            display: inline-block;
            background-color: #DBEAFE;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px;
            }
            .otp-code {
                font-size: 36px;
                letter-spacing: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>🎓 SchoolSavvy</h1>
            <p>Password Reset Verification</p>
        </div>
        
        <div class="content">
            <h2>Hello {{ $userName }},</h2>
            
            <p>We received a request to reset the password for your <span class="badge">{{ $userType }}</span> account.</p>
            
            <p>Use the following One-Time Password (OTP) to reset your password:</p>
            
            <div class="otp-container">
                <div class="otp-label">Your OTP Code</div>
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-note">⏱️ Valid for {{ $expiresIn }}</div>
            </div>
            
            <div class="info-box">
                <strong>📱 How to use this OTP:</strong>
                <p>1. Go to the password reset page in your app</p>
                <p>2. Enter your email address</p>
                <p>3. Enter the OTP code above</p>
                <p>4. Create your new password</p>
            </div>
            
            <div class="warning-box">
                <strong>⚠️ Security Notice:</strong>
                <ul>
                    <li>This OTP will expire in <strong>{{ $expiresIn }}</strong></li>
                    <li>Never share this OTP with anyone, including SchoolSavvy staff</li>
                    <li>If you didn't request this password reset, please ignore this email</li>
                    <li>Your password will remain unchanged if you don't use this OTP</li>
                </ul>
            </div>
            
            <div class="divider"></div>
            
            <p style="color: #6b7280; font-size: 14px;">
                If you continue to have problems, please contact our support team at 
                <a href="mailto:hello@schoolsavvy.com" style="color: #3B82F6;">hello@schoolsavvy.com</a>
            </p>
            
            <p style="margin-top: 30px; color: #6b7280;">
                Best regards,<br>
                <strong style="color: #1f2937;">The SchoolSavvy Team</strong>
            </p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} SchoolSavvy. All rights reserved.</p>
            <p style="margin-top: 10px;">This is an automated security email. Please do not reply.</p>
            <p style="margin-top: 10px; font-size: 12px;">
                <a href="mailto:hello@schoolsavvy.com">Contact Support</a> | 
                <a href="#">Privacy Policy</a> | 
                <a href="#">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
