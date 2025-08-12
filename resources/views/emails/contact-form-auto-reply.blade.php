<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you for your interest in SchoolSavvy</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
        }

        .header {
            background-color: #2563eb;
            color: white;
            padding: 20px;
            margin: -30px -30px 30px -30px;
            border-radius: 8px 8px 0 0;
        }

        .section {
            margin-bottom: 25px;
        }

        .highlight {
            background-color: #f0f9ff;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #2563eb;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .feature {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 4px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }

        .cta-button {
            background-color: #2563eb;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">Thank You, {{ $submission->full_name }}!</h1>
            <p style="margin: 10px 0 0 0;">We've received your inquiry about SchoolSavvy</p>
        </div>

        <div class="section">
            <p>Thank you for your interest in SchoolSavvy, the comprehensive school management platform designed to streamline your educational institution's operations.</p>

            <div class="highlight">
                <h3 style="margin-top: 0;">What happens next?</h3>
                <ul style="margin-bottom: 0;">
                    <li>Our team will review your inquiry within <strong>2-4 hours</strong></li>
                    <li>We'll contact you within <strong>24 hours</strong> to discuss your specific needs</li>
                    <li>Schedule a personalized demo tailored to {{ $submission->school_name }}</li>
                    <li>Receive a custom quote based on your requirements</li>
                </ul>
            </div>
        </div>

        <div class="section">
            <h3>Why SchoolSavvy?</h3>
            <div class="features">
                <div class="feature">
                    <h4>🎯 Modular System</h4>
                    <p>Pay only for the modules you need, starting from $15/month</p>
                </div>
                <div class="feature">
                    <h4>📊 Complete Analytics</h4>
                    <p>Student performance, attendance tracking, and detailed reports</p>
                </div>
                <div class="feature">
                    <h4>🔐 Multi-Role Access</h4>
                    <p>Secure access for admins, teachers, parents, and students</p>
                </div>
                <div class="feature">
                    <h4>📱 API-Ready</h4>
                    <p>100+ endpoints ready for mobile app integration</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h3>Your Submission Details:</h3>
            <p>
                <strong>School/Institution:</strong> {{ $submission->school_name }}<br>
                <strong>Your Role:</strong> {{ $submission->user_role }}<br>
                @if($submission->total_students)
                <strong>Students/Contact:</strong> {{ $submission->total_students }}<br>
                @endif
                <strong>Submitted:</strong> {{ $submission->created_at->format('F j, Y \a\t g:i A') }}
            </p>
        </div>

        <div class="section">
            <p>In the meantime, feel free to explore our features and pricing on our website, or reply to this email if you have any urgent questions.</p>

            <a href="https://schoolsavvy.com" class="cta-button">Visit Our Website</a>
        </div>

        <div class="footer">
            <p><strong>SchoolSavvy Team</strong><br>
                Email: hello@schoolsaavy.com<br>
                Website: schoolsavvy.com</p>

            <p style="margin-top: 15px; font-size: 12px;">
                This is an automated response to confirm we received your inquiry.
                Our team will follow up with you personally within 24 hours.
            </p>
        </div>
    </div>
</body>

</html>