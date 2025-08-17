# 🎓 Student Promotion System API Documentation

## Overview
The Student Promotion System is a comprehensive solution for managing academic years and student promotions in SchoolSavvy. It provides automated evaluation based on attendance, assignments, and assessments, with manual override capabilities.

## 🗓️ Academic Year Management

### Get All Academic Years
```http
GET /api/academic-years
Authorization: Bearer {token}
```

**Response:**
```json
{
  "status": "success",
  "message": "Academic years retrieved successfully",
  "data": [
    {
      "id": 1,
      "year_label": "2024-25",
      "display_name": "Academic Year 2024-2025",
      "start_date": "2024-04-01",
      "end_date": "2025-03-31",
      "is_current": true,
      "status": "active",
      "promotion_period": {
        "start_date": "2025-02-01",
        "end_date": "2025-04-15",
        "is_active": false,
        "days_remaining": 201
      },
      "statistics": {
        "total_students": 150,
        "promoted": 0,
        "failed": 0,
        "pending": 0
      },
      "criteria_count": 12
    }
  ]
}
```

### Create Academic Year
```http
POST /api/academic-years
Authorization: Bearer {token}
Content-Type: application/json

{
  "year_label": "2025-26",
  "display_name": "Academic Year 2025-2026",
  "start_date": "2025-04-01",
  "end_date": "2026-03-31",
  "promotion_start_date": "2026-02-01",
  "promotion_end_date": "2026-04-15",
  "is_current": false,
  "status": "upcoming"
}
```

### Set Academic Year as Current
```http
POST /api/academic-years/{id}/set-current
Authorization: Bearer {token}
```

### Start Promotion Period
```http
POST /api/academic-years/{id}/start-promotion
Authorization: Bearer {token}
```

## 🎯 Promotion Criteria Management

### Get Promotion Criteria for Academic Year
```http
GET /api/promotions/criteria/{academicYearId}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "from_class": {
        "id": 1,
        "name": "Grade 1A",
        "grade_level": 3
      },
      "to_class": {
        "id": 2,
        "name": "Grade 2A", 
        "grade_level": 4
      },
      "minimum_attendance_percentage": 75.00,
      "minimum_assignment_average": 40.00,
      "minimum_assessment_average": 40.00,
      "minimum_overall_percentage": 45.00,
      "promotion_weightages": {
        "attendance": 25,
        "assignments": 35,
        "assessments": 40
      },
      "grace_marks_allowed": 5.00,
      "allow_conditional_promotion": true,
      "has_remedial_option": true,
      "remedial_subjects": ["English", "Mathematics"]
    }
  ]
}
```

### Create/Update Promotion Criteria
```http
POST /api/promotions/criteria
Authorization: Bearer {token}
Content-Type: application/json

{
  "academic_year_id": 1,
  "from_class_id": 1,
  "to_class_id": 2,
  "minimum_attendance_percentage": 75.00,
  "minimum_assignment_average": 50.00,
  "minimum_assessment_average": 50.00,
  "minimum_overall_percentage": 50.00,
  "promotion_weightages": {
    "attendance": 20,
    "assignments": 40,
    "assessments": 40
  },
  "grace_marks_allowed": 5.00,
  "allow_conditional_promotion": true,
  "has_remedial_option": true,
  "remedial_subjects": ["English", "Mathematics", "Science"]
}
```

## 📊 Student Evaluation & Promotion

### Evaluate Single Student
```http
POST /api/promotions/evaluate-student
Authorization: Bearer {token}
Content-Type: application/json

{
  "student_id": 123,
  "academic_year_id": 1
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Student evaluated successfully",
  "data": {
    "id": 1,
    "student_id": 123,
    "academic_year_id": 1,
    "from_class_id": 1,
    "to_class_id": 2,
    "promotion_status": "promoted",
    "attendance_percentage": 85.50,
    "assignment_average": 75.25,
    "assessment_average": 68.00,
    "final_percentage": 72.45,
    "promotion_reason": "Meets all promotion criteria",
    "requires_remedial": false,
    "parent_meeting_required": false,
    "evaluation_date": "2025-08-17T10:30:00Z"
  }
}
```

### Bulk Evaluate Students
```http
POST /api/promotions/bulk-evaluate
Authorization: Bearer {token}
Content-Type: application/json

{
  "academic_year_id": 1,
  "class_ids": [1, 2, 3],
  "batch_name": "End of Year Promotions 2025",
  "description": "Bulk evaluation for all Grade 1-3 students"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Bulk evaluation started successfully",
  "data": {
    "id": 1,
    "batch_name": "End of Year Promotions 2025",
    "status": "processing",
    "total_students": 150,
    "processed_students": 0,
    "promoted_students": 0,
    "failed_students": 0,
    "pending_students": 150
  }
}
```

### Apply Promotions
```http
POST /api/promotions/apply-promotions
Authorization: Bearer {token}
Content-Type: application/json

{
  "academic_year_id": 1,
  "promotion_ids": [1, 2, 3, 4]  // Optional: specific promotions to apply
}
```

### Override Promotion Decision
```http
POST /api/promotions/{promotionId}/override
Authorization: Bearer {token}
Content-Type: application/json

{
  "new_status": "promoted",
  "reason": "Exceptional performance in extracurricular activities"
}
```

## 📈 Reports & Statistics

### Get Promotion Statistics
```http
GET /api/promotions/statistics/{academicYearId}
Authorization: Bearer {token}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "total_students": 150,
    "promoted": 125,
    "conditionally_promoted": 15,
    "failed": 8,
    "pending": 2,
    "requiring_remedial": 20,
    "parent_meetings_required": 5,
    "promotion_rate": 93.33
  }
}
```

### Get Student Promotions
```http
GET /api/promotions/students/{academicYearId}
Authorization: Bearer {token}
```

### Get Promotion Batches
```http
GET /api/promotions/batches/{academicYearId}
Authorization: Bearer {token}
```

## 🔧 Advanced Features

### Generate Next Academic Year Template
```http
GET /api/academic-years/{id}/generate-next
Authorization: Bearer {token}
```

### Clone Promotion Criteria
```http
POST /api/academic-years/{id}/clone-criteria
Authorization: Bearer {token}
Content-Type: application/json

{
  "from_academic_year_id": 1
}
```

## 📋 Promotion Status Values

- `pending` - Not yet evaluated
- `promoted` - Successfully promoted to next grade
- `conditionally_promoted` - Promoted with conditions/requirements
- `failed` - Not promoted, will repeat current grade
- `transferred` - Moved to different school/section
- `graduated` - Completed final year
- `withdrawn` - Left school

## 🎯 Remedial Status Values

- `not_required` - No remedial work needed
- `pending` - Remedial work assigned but not started
- `in_progress` - Currently working on remedial requirements
- `completed` - Successfully completed remedial work
- `failed` - Failed to complete remedial requirements

## 🏫 Module Access

All promotion system endpoints require the `promotion-system` module to be active for the school. The system automatically checks module access and returns a `403 Module Access Denied` error if the module is not available.

## 🔐 Authentication

All endpoints require a valid Bearer token obtained through the `/api/auth/login` endpoint. The token must belong to a user with appropriate permissions (admin, teacher, or super_admin).

## 📚 Error Handling

Standard error responses follow the SchoolSavvy API format:

```json
{
  "status": "error",
  "message": "Error description",
  "errors": {
    "field_name": ["Specific validation error"]
  }
}
```

## 🚀 Getting Started

1. Ensure the `promotion-system` module is activated for your school
2. Create academic years using the academic year endpoints
3. Set up promotion criteria for each class/grade level
4. Run student evaluations at the end of academic periods
5. Review and apply promotions
6. Generate reports and statistics

The system integrates seamlessly with your existing student performance data, attendance records, and assignment submissions to provide comprehensive promotion decisions.
