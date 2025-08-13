# Parent API Documentation

This document describes the Parent APIs for the SchoolSavvy mobile application.

## Authentication

All parent APIs require authentication using Bearer token with `user_type: parent`.

```
Authorization: Bearer {token}
```

## Base URL

```
/api/parent
```

## Rate Limiting

- **Default**: 60 requests per minute per authenticated user
- **Statistics API**: Cached for 5 minutes for optimal performance

---

## 1. Get Parent's Students

Get detailed list of all students (children) associated with the authenticated parent.

### Endpoint
```http
GET /api/parent/children
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Response
```json
{
    "success": true,
    "message": "Students retrieved successfully.",
    "data": {
        "students": [
            {
                "id": 1,
                "admission_number": "ADM011",
                "roll_number": "ROLL011",
                "first_name": "Keshawn",
                "last_name": "Corwin",
                "date_of_birth": "2015-02-06T18:30:00.000000Z",
                "gender": "male",
                "admission_date": "1984-09-24T18:30:00.000000Z",
                "blood_group": "O+",
                "profile_photo": null,
                "address": "68903 Nikko Hills\nNorth Brionnaside, PA 60740",
                "phone": "9514550898",
                "is_active": true,
                "created_at": "2025-07-20T22:54:52.000000Z",
                "updated_at": "2025-07-20T22:54:52.000000Z",
                "class_id": 5,
                "class_name": "Grade 5",
                "class_section": "A",
                "class_title": "Grade 5 - A",
                "school_id": 1,
                "school_name": "Cambridge International School",
                "full_name": "Keshawn Corwin"
            }
        ],
        "total_students": 1
    }
}
```

---

## 2. Get Student Statistics

Get comprehensive statistics for a specific student (child).

### Endpoint
```http
POST /api/parent/student/statistics
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body
```json
{
    "student_id": 123
}
```

### Response
```json
{
    "success": true,
    "message": "Student statistics retrieved successfully.",
    "data": {
        "student_info": {
            "id": 123,
            "name": "John Doe",
            "admission_number": "CIS001",
            "class": "Grade 5 - A",
            "school": "Cambridge International School"
        },
        "attendance": {
            "total_days": 22,
            "present_days": 20,
            "absent_days": 2,
            "late_days": 0,
            "attendance_percentage": 90.91,
            "this_month": "August 2025"
        },
        "assignments": {
            "total_assignments": 15,
            "pending_submissions": 3,
            "submitted_assignments": 12,
            "graded_assignments": 10,
            "overdue_assignments": 1,
            "average_grade": 85.5
        },
        "assessments": {
            "total_exams": 4,
            "passed_exams": 4,
            "failed_exams": 0,
            "average_marks": 87.25,
            "average_percentage": 87.25,
            "this_month": "August 2025"
        },
        "fees": {
            "total_fees": 50000.00,
            "paid_amount": 30000.00,
            "pending_amount": 20000.00,
            "overdue_amount": 5000.00,
            "payment_status": "pending"
        },
        "events": [
            {
                "id": 1,
                "title": "Annual Sports Day",
                "start_date": "2025-08-20",
                "end_date": "2025-08-20",
                "type": "sports",
                "days_remaining": 7
            }
        ],
        "recent_activity": {
            "recent_attendance": [
                {
                    "date": "2025-08-12",
                    "status": "present"
                }
            ],
            "recent_submissions": [
                {
                    "assignment_id": 5,
                    "status": "graded",
                    "submitted_at": "2025-08-10 14:30:00",
                    "marks_obtained": 85
                }
            ],
            "recent_grades": [
                {
                    "assessment_id": 3,
                    "marks_obtained": 90,
                    "percentage": 90.0,
                    "grade": "A+",
                    "result_published_at": "2025-08-11 10:00:00"
                }
            ]
        }
    },
    "generated_at": "2025-08-13T10:30:00.000000Z"
}
```

---

## 3. Get Student Attendance

Get detailed attendance records for a specific student.

### Endpoint
```http
POST /api/parent/student/attendance
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body
```json
{
    "student_id": 123,
    "month": 8,
    "year": 2025,
    "limit": 30
}
```

### Parameters
- `student_id` (required): Student ID
- `month` (optional): Month (1-12), defaults to current month
- `year` (optional): Year, defaults to current year
- `limit` (optional): Records limit (10-100), defaults to 30

### Response
```json
{
    "success": true,
    "message": "Student attendance retrieved successfully.",
    "data": {
        "attendance_records": [
            {
                "date": "2025-08-12",
                "status": "present",
                "check_in_time": "08:30:00",
                "check_out_time": "15:00:00",
                "remarks": null
            },
            {
                "date": "2025-08-11",
                "status": "absent",
                "check_in_time": null,
                "check_out_time": null,
                "remarks": "Sick leave"
            }
        ],
        "month": 8,
        "year": 2025,
        "total_records": 22
    }
}
```

---

## 4. Get Student Assignments

Get assignments for a specific student with submission status.

### Endpoint
```http
POST /api/parent/student/assignments
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body
```json
{
    "student_id": 123,
    "status": "pending",
    "limit": 20
}
```

### Parameters
- `student_id` (required): Student ID
- `status` (optional): Filter by status (`pending`, `submitted`, `graded`, `overdue`)
- `limit` (optional): Records limit (10-50), defaults to 20

### Response
```json
{
    "success": true,
    "message": "Student assignments retrieved successfully.",
    "data": {
        "assignments": [
            {
                "id": 5,
                "title": "Mathematics Chapter 5 Exercise",
                "description": "Complete exercises 1-20 from chapter 5",
                "subject": "Mathematics",
                "teacher": "Mrs. Smith",
                "assigned_date": "2025-08-10",
                "due_date": "2025-08-15",
                "max_marks": 100,
                "status": "submitted",
                "submission": {
                    "submitted_at": "2025-08-14 16:30:00",
                    "marks_obtained": 85,
                    "grade_percentage": 85.0,
                    "teacher_feedback": "Good work! Keep it up.",
                    "is_late_submission": false
                }
            },
            {
                "id": 6,
                "title": "Science Project - Solar System",
                "description": "Create a model of the solar system",
                "subject": "Science",
                "teacher": "Mr. Johnson",
                "assigned_date": "2025-08-12",
                "due_date": "2025-08-20",
                "max_marks": 50,
                "status": "pending",
                "submission": null
            }
        ],
        "total_assignments": 2,
        "filtered_by": {
            "status": "pending"
        }
    }
}
```

---

## 5. Refresh Student Statistics

Clear cache and get fresh statistics for a student.

### Endpoint
```http
POST /api/parent/student/statistics/refresh
```

### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body
```json
{
    "student_id": 123
}
```

### Response
```json
{
    "success": true,
    "message": "Student statistics refreshed successfully.",
    "data": {
        // Same structure as Get Student Statistics
    },
    "refreshed_at": "2025-08-13T10:35:00.000000Z"
}
```

---

## Error Responses

### Authentication Error
```json
{
    "success": false,
    "message": "Access denied. Only parents can access this resource.",
}
```
**Status Code:** `403`

### Validation Error
```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {
        "student_id": ["The student id field is required."]
    }
}
```
**Status Code:** `422`

### Parent-Student Relationship Error
```json
{
    "success": false,
    "message": "Student does not belong to this parent."
}
```
**Status Code:** `403`

### Server Error
```json
{
    "success": false,
    "message": "Failed to retrieve student statistics.",
    "error": "Internal server error details"
}
```
**Status Code:** `500`

---

## Performance Features

1. **Caching**: Statistics are cached for 5 minutes for optimal performance
2. **Optimized Queries**: Uses efficient database queries with proper indexing
3. **Relationship Verification**: Fast parent-student relationship validation
4. **Selective Loading**: Only loads necessary data for each endpoint
5. **Rate Limiting**: Prevents API abuse while allowing normal usage

## Security Features

1. **Authentication Required**: All endpoints require valid parent authentication
2. **Relationship Verification**: Ensures parents can only access their children's data
3. **Input Validation**: Comprehensive validation on all input parameters
4. **SQL Injection Protection**: Using Laravel's ORM and parameterized queries
5. **Access Control**: Role-based access with parent-specific middleware
