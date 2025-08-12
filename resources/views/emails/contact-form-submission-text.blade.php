NEW CONTACT FORM SUBMISSION - SCHOOLSAVVY
========================================

Full Name: {{ $submission->full_name }}
Email: {{ $submission->email }}
School/Institution: {{ $submission->school_name }}
User Role: {{ $submission->user_role }}
@if($submission->total_students)
Total Students/Phone: {{ $submission->total_students }}
@endif

@if($submission->message)
Message:
{{ $submission->message }}
@endif

Submission Details:
Date: {{ $submission->created_at->format('F j, Y \a\t g:i A') }}
IP Address: {{ $submission->ip_address }}
User Agent: {{ $submission->user_agent }}

@if($securityScore)
Security Analysis:
Risk Level: {{ ucfirst($securityScore['risk_level'] ?? 'low') }}
Security Score: {{ $securityScore['total_score'] ?? 0 }}/100
@if(isset($securityScore['flags']) && count($securityScore['flags']) > 0)
Security Flags:
@foreach($securityScore['flags'] as $flag)
- {{ $flag }}
@endforeach
@endif
@endif

Next Steps:
- Review the submission details above
- Check the security analysis for any potential issues
- Contact the prospect within 24 hours for best conversion rates
- Schedule a demo or provide additional information as requested

Reply directly to this email to respond to {{ $submission->full_name }} at {{ $submission->email }}.

--
SchoolSavvy Contact Form System