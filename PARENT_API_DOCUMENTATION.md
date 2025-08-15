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

## 4. Get Student Assignments (with Pagination)

Get assignments for a specific student with submission status and pagination support.

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
    "per_page": 15,
    "page": 1
}
```

### Parameters
- `student_id` (required): Student ID
- `status` (optional): Filter by status (`pending`, `submitted`, `graded`, `overdue`)
- `per_page` (optional): Items per page (5-50), defaults to 15
- `page` (optional): Page number, defaults to 1

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
        "pagination": {
            "current_page": 1,
            "per_page": 15,
            "total": 45,
            "last_page": 3,
            "from": 1,
            "to": 15,
            "has_more_pages": true
        },
        "filtered_by": {
            "status": "pending"
        }
    }
}
```

### Pagination Details
- **current_page**: Current page number
- **per_page**: Number of items per page
- **total**: Total number of assignments
- **last_page**: Last page number
- **from**: First item number on current page
- **to**: Last item number on current page
- **has_more_pages**: Boolean indicating if more pages exist

### Notes
- When filtering by status, pagination counts reflect only filtered results
- Status filtering is applied at the application level for accurate status calculation
- Results are ordered by due date (most recent first)

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

## 6. Get Assignment Details

Get complete details for a specific assignment including submission status, class performance, and all assignment information.

### Endpoint
```http
POST /api/parent/student/assignment/details
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
    "assignment_id": 456
}
```

### Response
```json
{
    "status": "success",
    "message": "Assignment details retrieved successfully.",
    "data": {
        "assignment": {
            "id": 456,
            "title": "Math Chapter 5 Exercise",
            "description": "Complete all exercises from Chapter 5: Algebra Basics",
            "instructions": "Solve all problems showing step-by-step work. Submit handwritten solutions.", // nullable
            "type": "homework",
            "status": "published",
            "assigned_date": "2025-08-10",
            "due_date": "2025-08-15",
            "due_time": "23:59", // nullable - can be null if no specific time
            "max_marks": 50, // nullable - can be null for feedback-only assignments
            "allow_late_submission": true,
            "grading_criteria": "Accuracy: 70%, Method: 20%, Presentation: 10%", // nullable
            "attachments": [ // can be empty array []
                {
                    "name": "chapter5_exercises.pdf",
                    "url": "https://example.com/storage/assignments/chapter5_exercises.pdf",
                    "size": 245760,
                    "type": "pdf"
                }
            ],
            "is_overdue": false,
            "days_until_due": 2, // can be negative for overdue
            "can_accept_submissions": true
        },
        "subject": {
            "id": 12,
            "name": "Mathematics"
        },
        "teacher": {
            "id": 34,
            "name": "Mrs. Sarah Johnson" // nullable if teacher is deleted
        },
        "class": {
            "id": 8,
            "name": "Grade 10",
            "section": "A",
            "full_name": "Grade 10 - A"
        },
        "student_status": "pending",
        "submission": null, // NULLABLE - null when no submission exists
        "class_performance": { // NULLABLE - null when no graded submissions exist
            "average_marks": 42.5,
            "highest_marks": 48,
            "lowest_marks": 32,
            "total_graded": 15,
            "pass_rate": 80.0
        },
        "submission_stats": {
            "total_students": 25,
            "submitted_count": 18,
            "pending_count": 7,
            "graded_count": 15,
            "submission_rate": 72.0,
            "grading_progress": 83.33
        }
    }
}
```

### Field Specifications

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `assignment.id` | integer | ❌ | Always present |
| `assignment.title` | string | ❌ | Always present |
| `assignment.description` | string | ❌ | Always present |
| `assignment.instructions` | string | ✅ | Can be null if no specific instructions |
| `assignment.due_time` | string | ✅ | Can be null if no specific time set |
| `assignment.max_marks` | integer | ✅ | Can be null for feedback-only assignments |
| `assignment.grading_criteria` | string | ✅ | Can be null if not specified |
| `assignment.attachments` | array | ❌ | Always array (empty if no attachments) |
| `teacher.name` | string | ✅ | Can be null if teacher account is deleted |
| `submission` | object | ✅ | **null** when student hasn't submitted |
| `submission.submission_text` | string | ✅ | Can be null if only file submitted |
| `submission.attachment_path` | string | ✅ | Can be null if only text submitted |
| `submission.marks_obtained` | integer | ✅ | null until graded |
| `submission.grade_percentage` | float | ✅ | null until graded |
| `submission.teacher_feedback` | string | ✅ | null until teacher provides feedback |
| `submission.graded_at` | string | ✅ | null until graded |
| `submission.graded_by` | integer | ✅ | null until graded |
| `class_performance` | object | ✅ | **null** when no graded submissions exist |

### Response with Submission
When student has submitted the assignment:
```json
{
    "status": "success",
    "message": "Assignment details retrieved successfully.",
    "data": {
        "assignment": {
            // Same as above
        },
        "subject": {
            // Same as above
        },
        "teacher": {
            // Same as above
        },
        "class": {
            // Same as above
        },
        "student_status": "graded",
        "submission": {
            "id": 789,
            "status": "graded",
            "submitted_at": "2025-08-14 14:30:00",
            "submission_text": "I have completed all exercises as requested. The solutions are attached as PDF files with detailed step-by-step work.",
            "attachments": [
                {
                    "name": "Math_Assignment_Chapter5.pdf",
                    "original_name": "Math_Assignment_Chapter5.pdf",
                    "filename": "20250814143000_a3B8kL9m.pdf",
                    "url": "https://schoolsavvy-bucket.s3.us-east-1.amazonaws.com/uploads/assignment/123/2025/08/20250814143000_a3B8kL9m.pdf",
                    "path": "uploads/assignment/123/2025/08/20250814143000_a3B8kL9m.pdf",
                    "type": "pdf",
                    "mime_type": "application/pdf",
                    "size": 245760,
                    "size_human": "240 KB",
                    "is_image": false,
                    "uploaded_at": "2025-08-14T14:30:00.000000Z",
                    "has_thumbnail": false
                },
                {
                    "name": "Working_Calculations.jpg",
                    "original_name": "Working_Calculations.jpg",
                    "filename": "20250814143015_x9C2mN5k.jpg",
                    "url": "https://schoolsavvy-bucket.s3.us-east-1.amazonaws.com/uploads/assignment/123/2025/08/20250814143015_x9C2mN5k.jpg",
                    "path": "uploads/assignment/123/2025/08/20250814143015_x9C2mN5k.jpg",
                    "type": "jpg",
                    "mime_type": "image/jpeg",
                    "size": 512000,
                    "size_human": "500 KB",
                    "is_image": true,
                    "uploaded_at": "2025-08-14T14:30:15.000000Z",
                    "has_thumbnail": true
                }
            ],
            "attachment_count": 2,
            "has_attachments": true,
            "has_text_content": true,
            "marks_obtained": 45,
            "grade_percentage": 90.0,
            "grade_letter": "A",
            "teacher_feedback": "Excellent work! Your step-by-step approach is very clear and the calculations are accurate. The presentation is neat and well-organized. Keep it up!",
            "grading_details": {
                "accuracy_score": 35,
                "method_score": 9,
                "presentation_score": 1,
                "bonus_points": 0,
                "deductions": 0,
                "notes": "Minor presentation issue in problem 3"
            },
            "is_late_submission": false,
            "graded_at": "2025-08-15 09:15:00",
            "graded_by": 34,
            "created_at": "2025-08-14 14:30:00",
            "updated_at": "2025-08-15 09:15:00",
            "submission_summary": {
                "type": "text_and_files",
                "description": "Text content with 2 file(s)",
                "details": {
                    "content_length": 128,
                    "content_preview": "I have completed all exercises as requested. The solutions are attached as PDF files with detailed step-by-step work.",
                    "file_count": 2,
                    "file_types": ["pdf", "jpg"],
                    "total_size": "740 KB"
                }
            }
        },
        "class_performance": {
            // Same as above
        },
        "submission_stats": {
            // Same as above
        }
    }
}
```

### Submission Summary Types

The `submission_summary` object provides a quick overview of what the student submitted:

#### Text and Files (`text_and_files`)
```json
{
    "type": "text_and_files",
    "description": "Text content with 2 file(s)",
    "details": {
        "content_length": 128,
        "content_preview": "I have completed all exercises...",
        "file_count": 2,
        "file_types": ["pdf", "jpg"],
        "total_size": "740 KB"
    }
}
```

#### Text Only (`text_only`)
```json
{
    "type": "text_only",
    "description": "Text submission",
    "details": {
        "content_length": 256,
        "content_preview": "Here are my answers to the math problems...",
        "word_count": 45
    }
}
```

#### Files Only (`files_only`)
```json
{
    "type": "files_only",
    "description": "3 file(s) uploaded",
    "details": {
        "file_count": 3,
        "file_types": ["pdf", "docx", "jpg"],
        "total_size": "1.2 MB"
    }
}
```

#### No Submission (`none`)
```json
{
    "type": "none",
    "description": "No submission yet",
    "details": []
}
```

### Attachment Object Structure

Each attachment in the `attachments` array contains detailed file information:

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Display name of the file |
| `original_name` | string | Original filename when uploaded |
| `filename` | string | System-generated secure filename |
| `url` | string | Direct download/view URL |
| `path` | string | Internal storage path |
| `type` | string | File extension (pdf, jpg, docx, etc.) |
| `mime_type` | string | MIME type (application/pdf, image/jpeg, etc.) |
| `size` | integer | File size in bytes |
| `size_human` | string | Human-readable file size (240 KB, 1.2 MB) |
| `is_image` | boolean | Whether the file is an image |
| `uploaded_at` | string | When the file was uploaded (ISO format) |
| `has_thumbnail` | boolean | Whether thumbnail generation was queued |

### Mobile App Usage Examples

#### Display Submission Overview
```javascript
const submission = response.data.submission;

if (submission) {
    console.log(`Status: ${submission.status}`);
    console.log(`Summary: ${submission.submission_summary.description}`);
    
    if (submission.has_text_content) {
        console.log(`Text: ${submission.submission_text}`);
    }
    
    if (submission.has_attachments) {
        console.log(`Files: ${submission.attachment_count} files uploaded`);
        submission.attachments.forEach(file => {
            console.log(`- ${file.name} (${file.size_human})`);
        });
    }
}
```

#### Handle Different File Types
```javascript
submission.attachments.forEach(file => {
    if (file.is_image) {
        // Display image preview
        showImagePreview(file.url);
    } else if (file.type === 'pdf') {
        // Show PDF viewer option
        showPdfViewer(file.url);
    } else {
        // Show download option
        showDownloadOption(file.name, file.url);
    }
});
```

### Student Status Values
- **`pending`**: Assignment not yet submitted and not overdue
- **`submitted`**: Assignment submitted but not yet graded
- **`graded`**: Assignment submitted and graded by teacher
- **`overdue`**: Assignment not submitted and past due date

### Assignment Types
- **`homework`**: Regular homework assignments
- **`classwork`**: In-class work and activities
- **`project`**: Long-term projects
- **`quiz`**: Short assessments
- **`assessment`**: Formal assessments and tests

---

## Error Responses

### Authentication Error
```json
{
    "status": "error",
    "message": "Access denied. Only parents can access this resource."
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
