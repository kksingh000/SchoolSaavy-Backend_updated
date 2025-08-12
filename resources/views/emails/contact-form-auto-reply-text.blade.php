Thank You, {{ $submission->full_name }}!
==========================================

Thank you for your interest in SchoolSavvy, the comprehensive school management platform designed to streamline your educational institution's operations.

What happens next?
- Our team will review your inquiry within 2-4 hours
- We'll contact you within 24 hours to discuss your specific needs
- Schedule a personalized demo tailored to {{ $submission->school_name }}
- Receive a custom quote based on your requirements

Why SchoolSavvy?

🎯 Modular System
Pay only for the modules you need, starting from $15/month

📊 Complete Analytics
Student performance, attendance tracking, and detailed reports

🔐 Multi-Role Access
Secure access for admins, teachers, parents, and students

📱 API-Ready
100+ endpoints ready for mobile app integration

Your Submission Details:
School/Institution: {{ $submission->school_name }}
Your Role: {{ $submission->user_role }}
@if($submission->total_students)
Students/Contact: {{ $submission->total_students }}
@endif
Submitted: {{ $submission->created_at->format('F j, Y \a\t g:i A') }}

In the meantime, feel free to explore our features and pricing on our website, or reply to this email if you have any urgent questions.

Visit our website: https://schoolsavvy.com

--
SchoolSavvy Team
Email: hello@schoolsaavy.com
Website: schoolsavvy.com

This is an automated response to confirm we received your inquiry.
Our team will follow up with you personally within 24 hours.