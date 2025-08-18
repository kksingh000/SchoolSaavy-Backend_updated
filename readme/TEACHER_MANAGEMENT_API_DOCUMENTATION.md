# Teacher Management Module - API Documentation

## Overview
The Teacher Management Module provides comprehensive functionality for managing teachers in the SchoolSavvy SaaS platform. It includes teacher profile management, authentication integration, and relationship management with classes, assignments, and schedules.

## Database Schema

### Teachers Table Structure
```sql
CREATE TABLE teachers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,                    -- Link to users table for authentication
    school_id BIGINT NOT NULL,                  -- Multi-tenant isolation
    employee_id VARCHAR(255) UNIQUE NOT NULL,   -- Unique employee identifier
    phone VARCHAR(255) NOT NULL,                -- Contact number
    date_of_birth DATE NOT NULL,                -- Birth date
    joining_date DATE NOT NULL,                 -- Employment start date
    gender ENUM('male', 'female', 'other'),     -- Gender
    qualification VARCHAR(255) NOT NULL,        -- Education qualifications
    profile_photo VARCHAR(255) NULL,            -- S3 path to profile photo
    address TEXT NOT NULL,                      -- Full address
    specializations JSON NULL,                  -- Array of subject specializations
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,                  -- Soft deletes enabled
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
);
```

### Key Relationships
- **User**: BelongsTo - For authentication and basic profile info
- **School**: BelongsTo - Multi-tenant isolation
- **Classes**: HasMany - Classes where teacher is class teacher
- **Assignments**: HasMany - Assignments created by teacher
- **ClassSchedules**: HasMany - Teacher's class schedules
- **AssignmentSubmissions**: HasMany - Submissions graded by teacher

## API Endpoints

### Base URL: `/api/teachers`

#### 1. Get All Teachers
```http
GET /api/teachers
```

**Query Parameters:**
- `per_page` (optional): Items per page (1-100, default: 15)
- `search` (optional): Search in name, email, employee_id, phone
- `gender` (optional): Filter by gender
- `qualification` (optional): Filter by qualification
- `specialization` (optional): Filter by specialization
- `joining_date_from` (optional): Filter by joining date range
- `joining_date_to` (optional): Filter by joining date range

**Response:**
```json
{
    "status": "success",
    "message": "Teachers retrieved successfully",
    "data": {
        "data": [
            {
                "id": 1,
                "user_id": 15,
                "employee_id": "TCH250001",
                "name": "John Doe",
                "email": "john.doe@school.com",
                "phone": "+1234567890",
                "date_of_birth": "1985-05-15",
                "joining_date": "2023-08-01",
                "years_of_experience": 2,
                "gender": "male",
                "qualification": "M.Sc. Mathematics",
                "specializations": ["Mathematics", "Physics"],
                "profile_photo": "uploads/teachers/1/2025/08/photo.jpg",
                "profile_photo_url": "https://schoolsaavy.s3.ap-south-1.amazonaws.com/uploads/teachers/1/2025/08/photo.jpg",
                "address": "123 Main Street, City",
                "is_active": true,
                "classes_count": 3,
                "created_at": "2025-08-18T10:00:00.000000Z",
                "updated_at": "2025-08-18T10:00:00.000000Z"
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 15,
            "total": 75,
            "from": 1,
            "to": 15,
            "has_more_pages": true,
            "prev_page_url": null,
            "next_page_url": "https://api.schoolsaavy.com/teachers?page=2"
        }
    }
}
```

#### 2. Create Teacher
```http
POST /api/teachers
```

**Request Body:**
```json
{
    "name": "Jane Smith",
    "email": "jane.smith@school.com",
    "password": "SecurePassword123",
    "employee_id": "TCH250002",
    "phone": "+1234567891",
    "date_of_birth": "1990-03-20",
    "joining_date": "2025-08-01",
    "gender": "female",
    "qualification": "B.Ed. English Literature",
    "address": "456 Oak Avenue, City",
    "specializations": ["English", "Literature"],
    "profile_photo": "uploads/profile/school_1/2025/08/teacher_photo.jpg"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Teacher created successfully",
    "data": {
        "id": 2,
        "employee_id": "TCH250002",
        "name": "Jane Smith",
        "email": "jane.smith@school.com",
        // ... other fields
    }
}
```

#### 3. Get Teacher Details
```http
GET /api/teachers/{id}
```

**Response:** Returns complete teacher profile with relationships

#### 4. Update Teacher
```http
PUT /api/teachers/{id}
```

**Request Body:** (All fields optional)
```json
{
    "name": "Updated Name",
    "email": "new.email@school.com",
    "phone": "+1234567892",
    "qualification": "M.Ed. Mathematics",
    "specializations": ["Advanced Mathematics", "Statistics"],
    "profile_photo": null  // Remove photo
}
```

#### 5. Delete Teacher
```http
DELETE /api/teachers/{id}
```

**Response:**
```json
{
    "status": "success",
    "message": "Teacher deleted successfully"
}
```

#### 6. Get Teacher's Classes
```http
GET /api/teachers/{id}/classes
```

**Response:**
```json
{
    "status": "success",
    "message": "Teacher classes retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Grade 10-A",
            "students_count": 30,
            "subject": {
                "id": 1,
                "name": "Mathematics",
                "code": "MATH10"
            },
            "academic_year": {
                "id": 1,
                "year": "2025-26",
                "is_current": true
            }
        }
    ]
}
```

#### 7. Get Teacher's Assignments
```http
GET /api/teachers/{id}/assignments?status=published&per_page=20
```

**Query Parameters:**
- `status` (optional): Filter by assignment status
- `per_page` (optional): Items per page

#### 8. Get Teacher Dashboard Statistics
```http
GET /api/teachers/{id}/dashboard-stats
```

**Response:**
```json
{
    "status": "success",
    "message": "Teacher statistics retrieved successfully",
    "data": {
        "total_classes": 3,
        "total_students": 75,
        "active_assignments": 12,
        "pending_gradings": 5,
        "recent_assignments": [
            {
                "id": 1,
                "title": "Mathematics Assignment 1",
                "due_date": "2025-08-25",
                "status": "published",
                "class": {"id": 1, "name": "Grade 10-A"},
                "subject": {"id": 1, "name": "Mathematics"}
            }
        ]
    }
}
```

#### 9. Search Teachers
```http
GET /api/teachers/search?q=john&per_page=15
```

**Query Parameters:**
- `q` (required): Search term (minimum 2 characters)
- `per_page` (optional): Items per page

#### 10. Generate Employee ID
```http
GET /api/teachers/generate-employee-id
```

**Response:**
```json
{
    "status": "success",
    "message": "Employee ID generated successfully",
    "data": {
        "employee_id": "TCH250003"
    }
}
```

## Validation Rules

### Create Teacher Request
- `name`: required, string, max 255 characters
- `email`: required, email, unique in users table
- `password`: required, string, minimum 8 characters
- `employee_id`: required, string, unique in teachers table
- `phone`: required, string, max 20 characters
- `date_of_birth`: required, date, must be before today
- `joining_date`: required, date
- `gender`: required, enum (male, female, other)
- `qualification`: required, string, max 255 characters
- `address`: required, string
- `specializations`: optional, array of strings
- `profile_photo`: optional, string (S3 path), max 500 characters

### Update Teacher Request
- All fields optional with same validation rules as create
- Email uniqueness check excludes current teacher's email
- Employee ID uniqueness check excludes current teacher's ID

## Features

### 1. Profile Photo Management
- Integrates with existing upload API (`/api/upload/single`)
- Supports S3 storage with automatic URL generation
- Uses `GeneratesFileUrls` trait for consistent URL handling

### 2. Multi-tenant Security
- All queries automatically filtered by `school_id`
- Middleware injection ensures proper school isolation
- No cross-school data leakage

### 3. User Account Integration
- Creates user account with teacher profile
- Supports password updates
- Maintains user-teacher relationship integrity

### 4. Advanced Search & Filtering
- Search across multiple fields simultaneously
- Support for date range filters
- Specialization-based filtering

### 5. Employee ID Generation
- Automatic unique ID generation per school
- Format: TCH + Year + Sequential Number (e.g., TCH250001)
- Handles concurrent requests safely

### 6. Relationship Management
- Tracks teacher's classes and assignments
- Provides dashboard statistics
- Supports soft deletes with integrity checks

### 7. Dashboard Statistics
- Real-time calculation of key metrics
- Performance-optimized queries
- Recent activity tracking

## Error Handling

### Common Error Responses

#### Validation Error (422)
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "email": ["The email has already been taken."],
        "employee_id": ["The employee id has already been taken."]
    }
}
```

#### Not Found (404)
```json
{
    "status": "error",
    "message": "Teacher not found"
}
```

#### Cannot Delete (500)
```json
{
    "status": "error",
    "message": "Cannot delete teacher with active assignments or classes"
}
```

## Usage Examples

### Frontend Integration

#### Create Teacher Flow
```javascript
// 1. Upload profile photo first (optional)
const uploadPhoto = async (photoFile) => {
    const formData = new FormData();
    formData.append('file', photoFile);
    formData.append('type', 'profile');
    
    const response = await fetch('/api/upload/single', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: formData
    });
    
    return response.json();
};

// 2. Create teacher with all data
const createTeacher = async (teacherData, photoFile) => {
    let profilePhotoPath = null;
    
    if (photoFile) {
        const uploadResult = await uploadPhoto(photoFile);
        profilePhotoPath = uploadResult.data.path;
    }
    
    const response = await fetch('/api/teachers', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
            ...teacherData,
            profile_photo: profilePhotoPath
        })
    });
    
    return response.json();
};
```

## Performance Considerations

1. **Pagination**: Always use pagination for teacher lists
2. **Eager Loading**: Relationships loaded only when needed
3. **Caching**: Consider caching dashboard statistics for high-traffic schools
4. **Indexing**: Database indexes on frequently searched fields
5. **File URLs**: Generated on-demand with consistent caching

## Security Features

1. **School Isolation**: Automatic filtering by school_id
2. **Input Validation**: Comprehensive request validation
3. **SQL Injection Prevention**: Using Eloquent ORM
4. **File Upload Security**: Validates file paths and types
5. **Authentication Required**: All endpoints require valid authentication

## Integration with Other Modules

- **Assignment Management**: Teacher-assignment relationships
- **Class Management**: Teacher-class assignments
- **Attendance System**: Teacher can mark attendance
- **Timetable Management**: Teacher schedule management
- **Assessment System**: Teacher creates and grades assessments

This Teacher Management Module provides a robust foundation for managing educational staff within the SchoolSavvy platform while maintaining security, performance, and scalability requirements.
