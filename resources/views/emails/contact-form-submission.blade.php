<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Form Submission</title>
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

        .label {
            font-weight: bold;
            color: #374151;
            margin-bottom: 5px;
        }

        .value {
            background-color: #f9fafb;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #2563eb;
        }

        .security-info {
            background-color: #fef3c7;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }

        .risk-high {
            border-left-color: #dc2626;
            background-color: #fef2f2;
        }

        .risk-medium {
            border-left-color: #f59e0b;
            background-color: #fffbeb;
        }

        .risk-low {
            border-left-color: #10b981;
            background-color: #f0fdf4;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">New Contact Form Submission</h1>
            <p style="margin: 10px 0 0 0;">SchoolSavvy Contact Form</p>
        </div>

        <div class="section">
            <div class="label">Full Name:</div>
            <div class="value">{{ $submission->full_name }}</div>
        </div>

        <div class="section">
            <div class="label">Email Address:</div>
            <div class="value">{{ $submission->email }}</div>
        </div>

        <div class="section">
            <div class="label">School/Institution:</div>
            <div class="value">{{ $submission->school_name }}</div>
        </div>

        <div class="section">
            <div class="label">User Role:</div>
            <div class="value">{{ $submission->user_role }}</div>
        </div>

        @if($submission->total_students)
        <div class="section">
            <div class="label">Total Students/Phone:</div>
            <div class="value">{{ $submission->total_students }}</div>
        </div>
        @endif

        @if($submission->message)
        <div class="section">
            <div class="label">Message:</div>
            <div class="value">{{ $submission->message }}</div>
        </div>
        @endif

        <div class="section">
            <div class="label">Submission Details:</div>
            <div class="value">
                <strong>Date:</strong> {{ $submission->created_at->format('F j, Y \a\t g:i A') }}<br>
                <strong>IP Address:</strong> {{ $submission->ip_address }}<br>
                <strong>User Agent:</strong> {{ Str::limit($submission->user_agent, 100) }}
            </div>
        </div>

        @if($securityScore)
        <div class="security-info risk-{{ $securityScore['risk_level'] ?? 'low' }}">
            <h3 style="margin-top: 0;">Security Analysis</h3>
            <p><strong>Risk Level:</strong> {{ ucfirst($securityScore['risk_level'] ?? 'low') }}</p>
            <p><strong>Security Score:</strong> {{ $securityScore['total_score'] ?? 0 }}/100</p>
            @if(isset($securityScore['flags']) && count($securityScore['flags']) > 0)
            <p><strong>Flags:</strong></p>
            <ul style="margin: 5px 0;">
                @foreach($securityScore['flags'] as $flag)
                <li>{{ $flag }}</li>
                @endforeach
            </ul>
            @endif
        </div>
        @endif

        <div class="footer">
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Review the submission details above</li>
                <li>Check the security analysis for any potential issues</li>
                <li>Contact the prospect within 24 hours for best conversion rates</li>
                <li>Schedule a demo or provide additional information as requested</li>
            </ul>

            <p style="margin-top: 20px;">
                <strong>Reply directly to this email</strong> to respond to {{ $submission->full_name }} at {{ $submission->email }}.
            </p>
        </div>
    </div>
</body>

</html>