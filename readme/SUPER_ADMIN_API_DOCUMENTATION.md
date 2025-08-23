# Super Admin API Documentation

## Overview
The Super Admin API provides platform-level management capabilities for the SchoolSavvy SaaS platform. Super Admins can manage schools, view analytics, and monitor platform usage without accessing individual school data.

## Authentication
All Super Admin APIs require:
- Bearer token authentication (`Authorization: Bearer {token}`)
- `user_type: 'super_admin'` in user profile
- Valid SuperAdmin profile linked to user

## Base URL
All Super Admin endpoints are prefixed with `/api/super-admin/`

---

## School Management APIs

### 1. Get All Schools
**GET** `/api/super-admin/schools`

**Query Parameters:**
- `per_page` (integer, optional): Items per page (default: 15, max: 100)

**Response:**
```json
{
  "status": "success",
  "message": "Schools retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Cambridge International School",
        "code": "CIS-001",
        "email": "info@cambridge.edu",
        "phone": "+1-555-1234",
        "address": "123 Education St, City",
        "website": "https://cambridge.edu",
        "is_active": true,
        "created_at": "2025-01-15T10:30:00Z",
        "students_count": 450,
        "teachers_count": 25,
        "parents_count": 380,
        "school_admin": {
          "user": {
            "id": 2,
            "name": "John Doe",
            "email": "admin@cambridge.edu"
          }
        }
      }
    ],
    "total": 1,
    "per_page": 15
  }
}
```

### 2. Get Filtered Schools
**GET** `/api/super-admin/schools/filtered`

**Query Parameters:**
- `status` (string, optional): `active` or `inactive`
- `search` (string, optional): Search in name, email, phone
- `created_from` (date, optional): Schools created after this date
- `created_to` (date, optional): Schools created before this date
- `per_page` (integer, optional): Items per page

**Response:** Same format as Get All Schools

### 3. Create School with Admin
**POST** `/api/super-admin/schools`

**Request Body:**
```json
{
  "name": "New School Name",
  "code": "NS-001",
  "address": "123 School Street",
  "phone": "+1-555-9999",
  "email": "info@newschool.edu",
  "website": "https://newschool.edu",
  "logo": "https://s3.amazonaws.com/logo.png",
  "admin_name": "Admin Name",
  "admin_email": "admin@newschool.edu",
  "admin_password": "SecurePassword123",
  "admin_password_confirmation": "SecurePassword123",
  "admin_phone": "+1-555-8888"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "School and admin created successfully",
  "data": {
    "id": 5,
    "name": "New School Name",
    "code": "NS-001",
    // ... school details
    "school_admin": {
      "user": {
        "id": 15,
        "name": "Admin Name",
        "email": "admin@newschool.edu"
      }
    }
  }
}
```

### 4. Get School Details
**GET** `/api/super-admin/schools/{id}`

**Response:**
```json
{
  "status": "success",
  "message": "School details retrieved successfully",
  "data": {
    "id": 1,
    "name": "Cambridge International School",
    // ... school details
    "students_count": 450,
    "teachers_count": 25,
    "school_admin": {
      "user": {
        "id": 2,
        "name": "John Doe",
        "email": "admin@cambridge.edu",
        "is_active": true
      }
    },
    "modules": [
      {
        "id": 1,
        "name": "student-management",
        "slug": "student-management",
        "pivot": {
          "status": "active",
          "activated_at": "2025-01-15T10:30:00Z"
        }
      }
    ]
  }
}
```

### 5. Update School
**PUT** `/api/super-admin/schools/{id}`

**Request Body:**
```json
{
  "name": "Updated School Name",
  "address": "New Address",
  "phone": "+1-555-0000",
  "email": "newemail@school.edu"
}
```

### 6. Toggle School Status
**PATCH** `/api/super-admin/schools/{id}/toggle-status`

**Response:**
```json
{
  "status": "success",
  "message": "School status updated successfully",
  "data": {
    "id": 1,
    "is_active": false
    // ... other school data
  }
}
```

### 7. Delete School
**DELETE** `/api/super-admin/schools/{id}`

**Response:**
```json
{
  "status": "success",
  "message": "School deleted successfully",
  "data": null
}
```

---

## Analytics & Reporting APIs

### 1. Platform Overview
**GET** `/api/super-admin/analytics/platform-overview`

**Response:**
```json
{
  "status": "success",
  "message": "Platform overview retrieved successfully",
  "data": {
    "total_schools": 25,
    "active_schools": 22,
    "inactive_schools": 3,
    "total_users": 15450,
    "schools_created_this_month": 3,
    "schools_created_today": 0
  }
}
```

### 2. School Analytics
**GET** `/api/super-admin/analytics/schools`

**Query Parameters:**
- `school_id` (integer, optional): Get analytics for specific school

**Response:**
```json
{
  "status": "success",
  "message": "School analytics retrieved successfully",
  "data": [
    {
      "school_id": 1,
      "school_name": "Cambridge International School",
      "total_students": 450,
      "total_teachers": 25,
      "total_parents": 380,
      "total_users": 855,
      "active_modules": [
        {
          "module_name": "student-management",
          "display_name": "student-management",
          "activated_at": "2025-01-15"
        }
      ],
      "media_stats": {
        "total_files": 1250,
        "total_images": 1100,
        "total_videos": 150,
        "total_size_mb": 2840.5
      }
    }
  ]
}
```

### 3. Module Usage Analytics
**GET** `/api/super-admin/analytics/modules/usage`

**Response:**
```json
{
  "status": "success",
  "message": "Module usage analytics retrieved successfully",
  "data": [
    {
      "module_id": 1,
      "module_name": "student-management",
      "display_name": "Student Management",
      "schools_using": 22,
      "usage_percentage": 88.0
    },
    {
      "module_id": 2,
      "module_name": "attendance-system", 
      "display_name": "Attendance System",
      "schools_using": 18,
      "usage_percentage": 72.0
    }
  ]
}
```

### 4. Media Statistics
**GET** `/api/super-admin/analytics/media/statistics`

**Query Parameters:**
- `school_id` (integer, optional): Filter by school
- `period` (string, optional): `today`, `week`, `month`, `year` (default: `month`)

**Response:**
```json
{
  "status": "success",
  "message": "Media statistics retrieved successfully",
  "data": {
    "total_files": 5420,
    "total_images": 4800,
    "total_videos": 620,
    "total_size_mb": 12450.8,
    "period": "month"
  }
}
```

### 5. User Growth Analytics
**GET** `/api/super-admin/analytics/users/growth`

**Query Parameters:**
- `period` (string, optional): `week`, `month`, `year` (default: `month`)

**Response:**
```json
{
  "status": "success",
  "message": "User growth analytics retrieved successfully",
  "data": {
    "students": [
      {"date": "2025-01-01", "count": 15},
      {"date": "2025-01-02", "count": 8},
      {"date": "2025-01-03", "count": 22}
    ],
    "teachers": [
      {"date": "2025-01-01", "count": 2},
      {"date": "2025-01-02", "count": 1}
    ],
    "schools": [
      {"date": "2025-01-15", "count": 1}
    ],
    "period": "month"
  }
}
```

### 6. Top Performing Schools
**GET** `/api/super-admin/analytics/schools/top-performing`

**Query Parameters:**
- `limit` (integer, optional): Number of schools to return (default: 10, max: 50)

**Response:**
```json
{
  "status": "success",
  "message": "Top performing schools retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Cambridge International School",
      "students_count": 450,
      "teachers_count": 25,
      "is_active": true,
      "created_at": "2025-01-15T10:30:00Z",
      "performance_score": 325
    }
  ]
}
```

### 7. Detailed School Analytics
**GET** `/api/super-admin/analytics/schools/{id}/detailed`

**Response:**
```json
{
  "status": "success",
  "message": "Detailed school analytics retrieved successfully",
  "data": {
    "school_analytics": {
      "school_id": 1,
      "school_name": "Cambridge International School",
      "total_students": 450,
      "total_teachers": 25,
      "total_parents": 380,
      "total_users": 855,
      "active_modules": [...],
      "media_stats": {...}
    },
    "media_statistics": {
      "total_files": 1250,
      "total_images": 1100,
      "total_videos": 150,
      "total_size_mb": 2840.5,
      "period": "month"
    }
  }
}
```

---

## Error Responses

### Authentication Error
```json
{
  "status": "error",
  "message": "Authentication required."
}
```

### Authorization Error
```json
{
  "status": "error",
  "message": "Access denied. Super admin privileges required."
}
```

### Validation Error
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."],
    "admin_password": ["The admin password confirmation does not match."]
  }
}
```

### Not Found Error
```json
{
  "status": "error",
  "message": "School not found"
}
```

### Server Error
```json
{
  "status": "error", 
  "message": "Failed to create school: Database connection error"
}
```

---

## Notes

1. **Data Privacy**: Super Admins can see school statistics and metadata but cannot access individual student, teacher, or parent records within schools.

2. **School Isolation**: Each school's data remains completely isolated. Super Admins only see aggregated counts and statistics.

3. **Module Management**: Super Admins can see which modules schools are using but cannot modify school module subscriptions through these APIs.

4. **Performance Scoring**: The performance score is calculated based on user count, activity, and other metrics. Higher scores indicate more active/successful schools.

5. **Soft Deletes**: Deleted schools are soft-deleted and can be restored if needed.

6. **Rate Limiting**: All APIs are subject to rate limiting to prevent abuse.

---

## Super Admin Authentication Flow

1. Login with super admin credentials:
   ```json
   POST /api/auth/login
   {
     "email": "superadmin@schoolsavvy.com",
     "password": "SuperAdmin@123",
     "user_type": "super_admin"
   }
   ```

2. Use the returned token for all subsequent requests:
   ```
   Authorization: Bearer {token}
   ```

3. All Super Admin routes will validate the user type and super admin profile automatically.
