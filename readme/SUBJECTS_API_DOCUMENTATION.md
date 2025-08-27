# Subjects API Documentation

## Overview
The Subjects API provides complete CRUD functionality for managing school subjects with pagination, search, and filtering capabilities.

## Base URL
All endpoints are prefixed with: `/api/subjects`

## Authentication
All endpoints require authentication via Laravel Sanctum token and school context injection.

---

## Endpoints

### 1. List Subjects (with Pagination)
**GET** `/api/subjects`

#### Query Parameters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `per_page` | integer | 15 | Number of items per page (max: 100) |
| `page` | integer | 1 | Page number |
| `search` | string | - | Search in name, code, or description |
| `status` | string | 'active' | Filter by status: 'active', 'inactive', 'all' |

#### Example Requests
```bash
# Basic pagination
GET /api/subjects?per_page=10&page=2

# Search subjects
GET /api/subjects?search=mathematics&per_page=5

# Get inactive subjects
GET /api/subjects?status=inactive

# Get all subjects (active + inactive)
GET /api/subjects?status=all

# Combined filters
GET /api/subjects?search=science&status=active&per_page=20
```

#### Response Format
```json
{
    "status": "success",
    "message": "Subjects retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "school_id": 1,
                "name": "Mathematics",
                "code": "MATH101",
                "description": "Basic mathematics for grade 1-5",
                "is_active": true,
                "created_at": "2025-08-26T10:30:00.000000Z",
                "updated_at": "2025-08-26T10:30:00.000000Z"
            },
            {
                "id": 2,
                "school_id": 1,
                "name": "English Literature",
                "code": "ENG201",
                "description": "Advanced English literature studies",
                "is_active": true,
                "created_at": "2025-08-26T11:00:00.000000Z",
                "updated_at": "2025-08-26T11:00:00.000000Z"
            }
        ],
        "first_page_url": "http://localhost/api/subjects?page=1",
        "from": 1,
        "last_page": 3,
        "last_page_url": "http://localhost/api/subjects?page=3",
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "active": false
            },
            {
                "url": "http://localhost/api/subjects?page=1",
                "label": "1",
                "active": true
            },
            {
                "url": "http://localhost/api/subjects?page=2",
                "label": "2",
                "active": false
            }
        ],
        "next_page_url": "http://localhost/api/subjects?page=2",
        "path": "http://localhost/api/subjects",
        "per_page": 15,
        "prev_page_url": null,
        "to": 15,
        "total": 42
    }
}
```

---

### 2. Create Subject
**POST** `/api/subjects`

#### Request Body
```json
{
    "name": "Physics",
    "code": "PHY301",
    "description": "Advanced physics for secondary students",
    "is_active": true
}
```

#### Response
```json
{
    "status": "success",
    "message": "Subject created successfully",
    "data": {
        "id": 15,
        "school_id": 1,
        "name": "Physics",
        "code": "PHY301",
        "description": "Advanced physics for secondary students",
        "is_active": true,
        "created_at": "2025-08-26T12:00:00.000000Z",
        "updated_at": "2025-08-26T12:00:00.000000Z"
    }
}
```

---

### 3. Get Single Subject
**GET** `/api/subjects/{id}`

#### Response
```json
{
    "status": "success",
    "message": "Subject retrieved successfully",
    "data": {
        "id": 15,
        "school_id": 1,
        "name": "Physics",
        "code": "PHY301",
        "description": "Advanced physics for secondary students",
        "is_active": true,
        "created_at": "2025-08-26T12:00:00.000000Z",
        "updated_at": "2025-08-26T12:00:00.000000Z"
    }
}
```

---

### 4. Update Subject
**PUT/PATCH** `/api/subjects/{id}`

#### Request Body
```json
{
    "name": "Advanced Physics",
    "code": "APHY301",
    "description": "Advanced physics with laboratory work",
    "is_active": false
}
```

---

### 5. Delete Subject
**DELETE** `/api/subjects/{id}`

#### Response
```json
{
    "status": "success",
    "message": "Subject deleted successfully",
    "data": null
}
```

#### Error Response (if subject is assigned to classes)
```json
{
    "status": "error",
    "message": "Cannot delete subject that is assigned to classes. Please remove from classes first."
}
```

---

### 6. Get Subjects by Class
**GET** `/api/subjects/class/{classId}`

Returns subjects assigned to a specific class.

---

## Validation Rules

### Create/Update Subject
| Field | Rules | Description |
|-------|-------|-------------|
| `name` | required, string, max:255 | Subject name |
| `code` | required, string, max:10, unique | Subject code (unique per school) |
| `description` | nullable, string | Subject description |
| `is_active` | boolean | Active status (default: true) |

## Error Handling

### Validation Errors (422)
```json
{
    "status": "error",
    "message": "The given data was invalid.",
    "errors": {
        "code": ["The code has already been taken."],
        "name": ["The name field is required."]
    }
}
```

### Not Found (404)
```json
{
    "status": "error",
    "message": "Subject not found"
}
```

## Features

### ✅ Implemented Features
- **Pagination**: Laravel's built-in pagination with customizable per_page
- **Search**: Full-text search across name, code, and description
- **Status Filtering**: Filter by active, inactive, or all subjects  
- **School Isolation**: Multi-tenant support with automatic school filtering
- **CRUD Operations**: Complete Create, Read, Update, Delete functionality
- **Validation**: Comprehensive input validation with custom error messages
- **Relationship Protection**: Prevents deletion of subjects assigned to classes

### 🔄 Usage Examples

#### Frontend Integration
```javascript
// Fetch paginated subjects with search
const response = await fetch('/api/subjects?search=math&per_page=10&page=1', {
    headers: {
        'Authorization': 'Bearer ' + token,
        'Accept': 'application/json'
    }
});

const result = await response.json();
console.log('Total subjects:', result.data.total);
console.log('Current page:', result.data.current_page);
console.log('Subjects:', result.data.data);
```

#### Mobile App Integration  
```dart
// Flutter/Dart example
Future<SubjectResponse> fetchSubjects({
    int page = 1, 
    int perPage = 15,
    String? search,
    String status = 'active'
}) async {
    final response = await http.get(
        Uri.parse('$baseUrl/api/subjects')
            .replace(queryParameters: {
                'page': page.toString(),
                'per_page': perPage.toString(),
                'search': search,
                'status': status,
            }),
        headers: {'Authorization': 'Bearer $token'}
    );
    
    return SubjectResponse.fromJson(json.decode(response.body));
}
```

---

## Performance Considerations

- Default page size is 15 items to balance performance and usability
- Search queries use database indexes on name and code fields  
- School-level filtering ensures data isolation and performance
- Lazy loading relationships to avoid N+1 query problems

## Security

- All endpoints require authentication
- School-level data isolation prevents cross-tenant data access
- Input validation prevents SQL injection and XSS attacks
- Unique constraints prevent duplicate subject codes within schools
