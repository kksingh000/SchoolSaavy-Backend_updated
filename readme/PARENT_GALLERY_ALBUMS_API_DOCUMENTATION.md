# Parent Gallery Albums API Documentation

## 📸 Student Gallery Albums API

This API provides a two-step gallery experience for parents:
1. **Albums API** - Shows gallery albums with thumbnails and media counts
2. **Album Media API** - Shows media items within a specific album

Both APIs use Laravel's built-in pagination for optimal performance.

---

## 🔗 API Endpoints

### 1. Get Student Gallery Albums
```
POST /api/parent/student/gallery/albums
```

### 2. Get Album Media Items  
```
POST /api/parent/student/gallery/album/media
```

---

## 🔐 Authentication
- Requires Bearer token authentication
- Only accessible to users with `user_type: 'parent'`
- Requires active `gallery-management` module for the school

---

## 📋 API 1: Get Student Gallery Albums

Shows all gallery albums that a student has access to, with thumbnail previews and media counts.

### Request Parameters

| Parameter   | Type     | Required | Default | Description |
|-------------|----------|----------|---------|-------------|
| student_id  | integer  | Yes      | -       | ID of the student (must belong to authenticated parent) |
| per_page    | integer  | No       | 15      | Albums per page (5-50) |
| page        | integer  | No       | 1       | Page number |

### Request Example

```json
{
    "student_id": 123,
    "per_page": 12,
    "page": 1
}
```

### Success Response (200 OK)

```json
{
    "status": "success",
    "message": "Student gallery albums retrieved successfully.",
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
        "albums": {
            "data": [
                {
                    "id": 23,
                    "title": "Science Exhibition 2025",
                    "description": "Annual science exhibition showcasing student projects",
                    "event_date": "2025-08-15",
                    "album_type": "class",
                    "class_name": "Grade 5 A",
                    "event_title": "Science Fair Week",
                    "creator_name": "Sarah Johnson",
                    "total_media_count": 45,
                    "photos_count": 38,
                    "videos_count": 6,
                    "documents_count": 1,
                    "cover_image": "/storage/gallery/science-exhibition-cover.jpg",
                    "thumbnails": [
                        {
                            "id": 156,
                            "title": "Project Display",
                            "url": "https://images.unsplash.com/photo-1581092921461-eab62e97a780?w=800&h=600",
                            "thumbnail_url": "https://images.unsplash.com/photo-1581092921461-eab62e97a780?w=300&h=225"
                        },
                        {
                            "id": 157,
                            "title": "Student Presentations",
                            "url": "https://images.unsplash.com/photo-1606761568499-6d2451b23c66?w=800&h=600",
                            "thumbnail_url": "https://images.unsplash.com/photo-1606761568499-6d2451b23c66?w=300&h=225"
                        },
                        {
                            "id": 158,
                            "title": "Awards Ceremony",
                            "url": "https://images.unsplash.com/photo-1523580494863-6f3031224c94?w=800&h=600",
                            "thumbnail_url": "https://images.unsplash.com/photo-1523580494863-6f3031224c94?w=300&h=225"
                        }
                    ],
                    "created_at": "2025-08-15T10:30:00.000000Z",
                    "updated_at": "2025-08-15T15:45:00.000000Z"
                },
                {
                    "id": 24,
                    "title": "Cultural Fest 2025",
                    "description": "Annual cultural festival performances",
                    "event_date": "2025-08-13",
                    "album_type": "school",
                    "class_name": null,
                    "event_title": "Cultural Week",
                    "creator_name": "Mike Wilson",
                    "total_media_count": 67,
                    "photos_count": 52,
                    "videos_count": 15,
                    "documents_count": 0,
                    "cover_image": "/storage/gallery/cultural-fest-cover.jpg",
                    "thumbnails": [
                        {
                            "id": 201,
                            "title": "Dance Performance",
                            "url": "https://images.unsplash.com/photo-1518834107812-67b0b7c58434?w=800&h=600",
                            "thumbnail_url": "https://images.unsplash.com/photo-1518834107812-67b0b7c58434?w=300&h=225"
                        },
                        {
                            "id": 202,
                            "title": "Music Concert",
                            "url": "https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=600",
                            "thumbnail_url": "https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=300&h=225"
                        }
                    ],
                    "created_at": "2025-08-13T14:20:00.000000Z",
                    "updated_at": "2025-08-13T16:30:00.000000Z"
                }
            ],
            "pagination": {
                "current_page": 1,
                "per_page": 12,
                "total": 8,
                "last_page": 1,
                "from": 1,
                "to": 8,
                "has_more_pages": false
            }
        }
    }
}
```

---

## 📋 API 2: Get Album Media Items

Shows paginated media items from a specific gallery album with filtering options.

### Request Parameters

| Parameter   | Type     | Required | Default | Description |
|-------------|----------|----------|---------|-------------|
| student_id  | integer  | Yes      | -       | ID of the student (must belong to authenticated parent) |
| album_id    | integer  | Yes      | -       | ID of the gallery album |
| media_type  | string   | No       | null    | Filter by media type: `'photo'`, `'video'`, `'document'` |
| per_page    | integer  | No       | 20      | Media items per page (5-50) |
| page        | integer  | No       | 1       | Page number |

### Request Example

```json
{
    "student_id": 123,
    "album_id": 23,
    "media_type": "photo",
    "per_page": 16,
    "page": 1
}
```

### Success Response (200 OK)

```json
{
    "status": "success",
    "message": "Album media retrieved successfully.",
    "data": {
        "album": {
            "id": 23,
            "title": "Science Exhibition 2025",
            "description": "Annual science exhibition showcasing student projects",
            "event_date": "2025-08-15",
            "album_type": "class",
            "class_name": "Grade 5 A",
            "event_title": "Science Fair Week",
            "total_media_count": 45,
            "cover_image": "https://s3.amazonaws.com/bucket/gallery/science-exhibition-cover.jpg"
        },
        "media": {
            "data": [
                {
                    "id": 156,
                    "type": "photo",
                    "title": "Science Fair Project Display",
                    "description": "Students showcasing their innovative science projects",
                    "file_url": "https://images.unsplash.com/photo-1581092921461-eab62e97a780?w=800&h=600&fit=crop",
                    "thumbnail_url": "https://images.unsplash.com/photo-1581092921461-eab62e97a780?w=400&h=300&fit=crop",
                    "file_size": 1248576,
                    "file_size_formatted": "1.2 MB",
                    "dimensions": {
                        "width": 800,
                        "height": 600
                    },
                    "duration": null,
                    "duration_formatted": null,
                    "views_count": 45,
                    "is_featured": true,
                    "created_at": "2025-08-15T10:30:00.000000Z",
                    "metadata": {
                        "camera": "Canon EOS R5",
                        "location": "Science Lab"
                    }
                },
                {
                    "id": 157,
                    "type": "video",
                    "title": "Student Presentation Video",
                    "description": "John explaining his volcano project",
                    "file_url": "https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_2mb.mp4",
                    "thumbnail_url": "https://images.unsplash.com/photo-1606761568499-6d2451b23c66?w=400&h=300&fit=crop",
                    "file_size": 15728640,
                    "file_size_formatted": "15 MB",
                    "dimensions": {
                        "width": 1280,
                        "height": 720
                    },
                    "duration": 180,
                    "duration_formatted": "3:00",
                    "views_count": 23,
                    "is_featured": false,
                    "created_at": "2025-08-15T11:15:00.000000Z",
                    "metadata": {
                        "bitrate": "2000 kbps",
                        "codec": "H.264"
                    }
                }
            ],
            "pagination": {
                "current_page": 1,
                "per_page": 16,
                "total": 38,
                "last_page": 3,
                "from": 1,
                "to": 16,
                "has_more_pages": true
            }
        },
        "summary": {
            "total_items": 38,
            "photos_count": 32,
            "videos_count": 5,
            "documents_count": 1
        }
    }
}
```

---

## ❌ Error Responses

### Invalid Student ID (422 Validation Error)
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

### Student Not Belongs to Parent (500 Internal Server Error)
```json
{
    "status": "error",
    "message": "Failed to retrieve student gallery albums.",
    "errors": "Student does not belong to this parent."
}
```

### Album Not Found or Not Accessible (500 Internal Server Error)
```json
{
    "status": "error",
    "message": "Failed to retrieve album media.",
    "errors": "Album not found or not accessible."
}
```

### Module Not Active (403 Forbidden)
```json
{
    "status": "error",
    "message": "Module access denied. The 'gallery-management' module is not active for your school.",
    "errors": null
}
```

---

## 📊 Data Structure Details

### Album Types

- **Class Albums** (`album_type: "class"`)
  - Specific to the student's class
  - Contains class activities, projects, events
  - `class_name` field populated

- **School Albums** (`album_type: "school"`)
  - School-wide content accessible to all students
  - General school events, announcements
  - `class_name` field is null

### Media Types

- **Photos** (`type: "photo"`)
  - JPEG, PNG, GIF, WebP, SVG files
  - Includes dimensions and camera metadata when available
  - Optimized thumbnails provided

- **Videos** (`type: "video"`)
  - MP4, AVI, MOV, WMV, FLV, WebM files
  - Includes duration, dimensions, and codec information
  - Video thumbnail previews

- **Documents** (`type: "document"`)
  - PDF, DOC, PPT, and other document files
  - File size and type information provided

### Thumbnails

- Up to 3 thumbnail images per album for preview
- Prioritizes featured images first, then by sort order
- Provides both full-size and thumbnail URLs
- Optimized for fast loading on mobile devices

---

## 🎯 Usage Examples

### Get All Albums for Student
```json
{
    "student_id": 123
}
```

### Get Albums with Custom Pagination
```json
{
    "student_id": 123,
    "per_page": 8,
    "page": 2
}
```

### Get All Media from Science Fair Album
```json
{
    "student_id": 123,
    "album_id": 23
}
```

### Get Only Photos from Album
```json
{
    "student_id": 123,
    "album_id": 23,
    "media_type": "photo",
    "per_page": 12
}
```

### Get Videos from Album (Second Page)
```json
{
    "student_id": 123,
    "album_id": 23,
    "media_type": "video",
    "per_page": 8,
    "page": 2
}
```

---

## 🔐 Security Features

1. **Parent-Student Relationship Verification**
   - Validates that the requested student belongs to the authenticated parent
   - Uses pivot table `parent_student` for verification

2. **School Data Isolation**
   - All queries are filtered by school ID
   - Prevents cross-school data access

3. **Album Access Control**
   - Only public albums are accessible
   - Class albums only accessible to students in that class
   - School-wide albums accessible to all school students

4. **Module Access Control**
   - Requires `gallery-management` module to be active
   - Follows established module access patterns

---

## 🚀 Performance Optimizations

1. **Laravel Pagination**
   - Uses Laravel's built-in pagination for efficient data loading
   - Optimized database queries with proper indexing

2. **Eager Loading**
   - Loads related models (class, event, creator) efficiently
   - Minimizes database queries with strategic eager loading

3. **Thumbnail Optimization**
   - Pre-generated thumbnails for fast loading
   - Multiple thumbnail sizes available
   - CDN-friendly URLs

4. **Query Optimization**
   - Selective field loading to reduce payload size
   - Proper database indexes for fast filtering
   - Efficient counting queries for media statistics

---

## 🎨 UI/UX Benefits

1. **Better User Experience**
   - Two-step navigation prevents overwhelming users
   - Album thumbnails provide visual context
   - Media counts help users know what to expect

2. **Mobile-Friendly**
   - Optimized pagination for mobile scrolling
   - Thumbnail sizes perfect for mobile screens
   - Efficient data loading reduces bandwidth usage

3. **Logical Organization**
   - Albums grouped by events and activities
   - Clear separation between class and school content
   - Intuitive navigation flow

4. **Performance**
   - Fast initial load with album overview
   - On-demand media loading
   - Efficient caching strategies

This API design provides a much more intuitive and efficient gallery experience for parents, with proper separation of concerns and optimal performance characteristics.
