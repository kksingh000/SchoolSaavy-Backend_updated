# Parent Gallery API Documentation

## 📸 Student Gallery API

This API allows parents to retrieve gallery content related to their child from class albums and school-wide gallery albums. **Note: This API only includes gallery albums and does not include assignment submissions.**

### 🔗 Endpoint
```
POST /api/parent/student/gallery
```

### 🔐 Authentication
- Requires Bearer token authentication
- Only accessible to users with `user_type: 'parent'`
- Requires active `gallery-management` module for the school

### 📝 Request Parameters

| Parameter   | Type     | Required | Default | Description |
|-------------|----------|----------|---------|-------------|
| student_id  | integer  | Yes      | -       | ID of the student (must belong to authenticated parent) |
| media_type  | string   | No       | null    | Filter by media type: `'photo'`, `'video'`, `'document'` |
| per_page    | integer  | No       | 15      | Items per page (5-50) |
| page        | integer  | No       | 1       | Page number |

### 📋 Request Example

```json
{
    "student_id": 123,
    "media_type": "photo",
    "per_page": 20,
    "page": 1
}
```

### ✅ Success Response (200 OK)

```json
{
    "status": "success",
    "message": "Student gallery retrieved successfully.",
    "data": {
        "student": {
            "id": 123,
            "name": "John Doe",
            "admission_number": "ADM001",
            "class": {
                "id": 45,
                "name": "Grade 5",
                "section": "A"
            }
        },
        "summary": {
            "total_items": 18,
            "photos_count": 15,
            "videos_count": 3,
            "documents_count": 0
        },
        "items": [
            {
                "id": "gallery_156",
                "type": "class_gallery",
                "media_type": "photo",
                "title": "Science Fair Project Display",
                "description": "Students showcasing their innovative science projects",
                "file_url": "https://images.unsplash.com/photo-1581092921461-eab62e97a780?w=800&h=600&fit=crop",
                "thumbnail_url": "https://images.unsplash.com/photo-1581092921461-eab62e97a780?w=400&h=300&fit=crop",
                "file_size": 1248576,
                "file_size_human": "1.2 MB",
                "dimensions": {
                    "width": 800,
                    "height": 600
                },
                "duration": null,
                "created_at": "2025-08-15T10:30:00.000000Z",
                "event_date": "2025-08-15",
                "album": {
                    "id": 23,
                    "title": "Science Exhibition 2025",
                    "description": "Annual science exhibition showcasing student projects"
                },
                "source": "Class Gallery",
                "views_count": 45,
                "is_featured": true
            },
            {
                "id": "school_gallery_157",
                "type": "school_gallery",
                "media_type": "video",
                "title": "Dance Performance Highlights",
                "description": "Students performing traditional dance",
                "file_url": "https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_2mb.mp4",
                "thumbnail_url": "https://images.unsplash.com/photo-1518834107812-67b0b7c58434?w=400&h=300&fit=crop",
                "file_size": 15728640,
                "file_size_human": "15 MB",
                "dimensions": {
                    "width": 1280,
                    "height": 720
                },
                "duration": 180,
                "created_at": "2025-08-13T14:20:00.000000Z",
                "event_date": "2025-08-13",
                "album": {
                    "id": 24,
                    "title": "Cultural Fest 2025",
                    "description": "Annual cultural festival performances"
                },
                "source": "School Gallery",
                "views_count": 67,
                "is_featured": false
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 18,
            "last_page": 1,
            "from": 1,
            "to": 18,
            "has_more_pages": false
        }
    }
}
```

### ❌ Error Responses

#### Invalid Student ID (422 Validation Error)
```json
{
    "status": "error",
    "message": "Validation failed.",
    "errors": {
        "student_id": [
            "The selected student id is invalid."
        ]
    }
}
```

#### Access Denied (403 Forbidden)
```json
{
    "status": "error",
    "message": "Access denied. Only parents can access this resource.",
    "errors": null
}
```

#### Student Not Belongs to Parent (500 Internal Server Error)
```json
{
    "status": "error",
    "message": "Failed to retrieve student gallery.",
    "errors": "Student does not belong to this parent."
}
```

#### Module Not Active (403 Forbidden)
```json
{
    "status": "error",
    "message": "Module access denied. The 'gallery-management' module is not active for your school.",
    "errors": null
}
```

### 📊 Response Data Structure

#### Item Types

1. **Class Gallery Items** (`type: "class_gallery"`)
   - Photos, videos, and documents from class-specific gallery albums
   - Associated with student's class activities and events
   - Contains album information and event details
   - Has view counts and featured status

2. **School Gallery Items** (`type: "school_gallery"`)
   - Content from school-wide gallery albums (not class-specific)
   - School events, announcements, and general activities
   - Accessible to all students in the school
   - Contains album information and event details

#### Media Types

- **Photos** (`media_type: "photo"`)
  - Image files (JPEG, PNG, GIF, WebP, SVG)
  - Includes dimensions when available
  - Thumbnail URLs provided

- **Videos** (`media_type: "video"`)
  - Video files (MP4, AVI, MOV, WMV, FLV, WebM)
  - Includes duration and dimensions when available
  - Thumbnail URLs for video previews

- **Documents** (`media_type: "document"`)
  - Document files (PDF, DOC, PPT, etc.)
  - From gallery uploads only

### 🔍 Filtering Options

#### By Media Type (`media_type` parameter)
- `null` (default) - All media types
- `"photo"` - Only image files
- `"video"` - Only video files
- `"document"` - Only document files

### 📋 Usage Examples

#### Get All Gallery Content
```json
{
    "student_id": 123,
    "per_page": 15
}
```

#### Get Only Photos
```json
{
    "student_id": 123,
    "media_type": "photo",
    "per_page": 20
}
```

#### Get Videos Only
```json
{
    "student_id": 123,
    "media_type": "video",
    "per_page": 12
}
```

#### Paginated Request
```json
{
    "student_id": 123,
    "per_page": 10,
    "page": 2
}
```

### 🔐 Security Features

1. **Parent-Student Relationship Verification**
   - Validates that the requested student belongs to the authenticated parent
   - Uses pivot table `parent_student` for verification

2. **School Data Isolation**
   - All queries are filtered by school ID
   - Prevents cross-school data access

3. **Module Access Control**
   - Requires `gallery-management` module to be active
   - Follows established module access patterns

4. **Public Content Only**
   - Only returns publicly accessible gallery albums
   - Content from `is_public: true` and `status: active` albums only

### 🏗️ Technical Implementation

#### Architecture Pattern
- Follows SchoolSavvy's Service-Controller pattern
- Business logic in `ParentService::getStudentGallery()`
- HTTP handling in `ParentController::getStudentGallery()`

#### Performance Optimizations
- Efficient database queries with proper relationships
- Manual pagination implementation for mixed data sources
- Optimized file URL generation
- Proper eager loading of album relationships

#### Data Sources
1. **Class Gallery System** - `gallery_albums` (class-specific) and `gallery_media` tables
2. **School Gallery System** - `gallery_albums` (school-wide) and `gallery_media` tables

#### File Access
- Gallery files use Storage URLs or external CDN URLs
- Thumbnail generation for supported media types
- Secure file serving through application routes

### 🚀 Integration Notes

This API seamlessly integrates with the existing SchoolSavvy architecture:

- Uses existing authentication and authorization middleware
- Follows established response format standards
- Implements proper module access control
- Maintains data isolation and security patterns
- Compatible with the parent mobile application structure

The endpoint provides a unified view of all gallery content related to a student's class and school activities, making it ideal for parent mobile applications wanting to display photos, videos, and documents from their child's school events and activities.

**Key Benefits:**
- Clean separation between gallery and assignment content
- Focus on visual storytelling of school activities
- Optimized for parent mobile app consumption
- Secure and performant implementation
- Consistent with SchoolSavvy architectural patterns
