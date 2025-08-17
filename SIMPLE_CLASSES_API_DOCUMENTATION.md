# Simple Classes API Documentation

## 📋 Overview

The Simple Classes API provides a fast, lightweight endpoint for retrieving basic class information (ID and name only). This endpoint is optimized for dropdowns, select lists, and other UI components that need quick class data without the overhead of full relationships and detailed information.

---

## 🚀 Endpoint Details

### **GET /api/classes/simple**

**Purpose**: Retrieve simplified class data for UI components like dropdowns and select lists.

**Performance**: Optimized for speed with minimal database load - only selects essential fields.

---

## 📊 Request Parameters

| Parameter | Type | Default | Range/Options | Description |
|-----------|------|---------|---------------|-------------|
| `page` | Integer | `1` | ≥ 1 | Page number for pagination |
| `per_page` | Integer | `15` | 1-100 | Number of items per page |
| `search` | String | - | - | Search term for filtering classes |

### Search Fields
The search functionality looks across multiple fields:
- **Class Name**: `name` field (e.g., "Grade 5", "Class A")
- **Section**: `section` field (e.g., "A", "B", "Morning") 
- **Grade Level**: `grade_level` field (e.g., "Primary", "Secondary")

---

## 📤 Response Format

### Success Response (200)
```json
{
    "status": "success",
    "message": "Simple classes retrieved successfully",
    "data": {
        "data": [
            {
                "id": 1,
                "name": "Grade 5A",
                "section": "A",
                "grade_level": "Primary"
            },
            {
                "id": 2,
                "name": "Grade 5B", 
                "section": "B",
                "grade_level": "Primary"
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 3,
            "per_page": 15,
            "total": 42,
            "from": 1,
            "to": 15,
            "has_more_pages": true,
            "prev_page_url": null,
            "next_page_url": "http://localhost:8080/api/classes/simple?page=2"
        }
    }
}
```

### Error Response (403 - Module Access Denied)
```json
{
    "status": "error",
    "message": "Access denied. The 'class-management' module is not active for your school.",
    "code": "MODULE_ACCESS_DENIED"
}
```

### Error Response (500 - Server Error)
```json
{
    "status": "error", 
    "message": "Failed to retrieve simple classes: [error details]"
}
```

---

## 💡 Usage Examples

### 1. **Basic Pagination**
```bash
# Get first page with default settings
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8080/api/classes/simple"

# Get second page with 20 items per page
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8080/api/classes/simple?page=2&per_page=20"
```

### 2. **Search Functionality**
```bash
# Search for classes containing "Grade 5"
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8080/api/classes/simple?search=Grade 5"

# Search for section "A" classes
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8080/api/classes/simple?search=A"

# Search with pagination
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost:8080/api/classes/simple?search=Primary&page=1&per_page=10"
```

### 3. **JavaScript/Frontend Integration**
```javascript
// Fetch classes for dropdown
async function loadClassOptions() {
    try {
        const response = await fetch('/api/classes/simple?per_page=50', {
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            const classes = result.data.data;
            populateDropdown(classes);
        }
    } catch (error) {
        console.error('Failed to load classes:', error);
    }
}

// Populate select dropdown
function populateDropdown(classes) {
    const select = document.getElementById('class-select');
    select.innerHTML = '<option value="">Select a class</option>';
    
    classes.forEach(cls => {
        const option = document.createElement('option');
        option.value = cls.id;
        option.textContent = `${cls.name} - ${cls.section}`;
        select.appendChild(option);
    });
}

// Search with debouncing
let searchTimeout;
function searchClasses(searchTerm) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        const response = await fetch(`/api/classes/simple?search=${encodeURIComponent(searchTerm)}`);
        const result = await response.json();
        updateSearchResults(result.data.data);
    }, 300);
}
```

### 4. **React/Vue.js Integration**
```javascript
// React Hook
import { useState, useEffect } from 'react';

function useSimpleClasses(search = '', page = 1) {
    const [classes, setClasses] = useState([]);
    const [pagination, setPagination] = useState({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setLoading(true);
        fetch(`/api/classes/simple?search=${search}&page=${page}`)
            .then(res => res.json())
            .then(data => {
                setClasses(data.data.data);
                setPagination(data.data.pagination);
                setLoading(false);
            })
            .catch(err => {
                console.error(err);
                setLoading(false);
            });
    }, [search, page]);

    return { classes, pagination, loading };
}

// Usage in component
function ClassSelector() {
    const { classes, loading } = useSimpleClasses();
    
    return (
        <select disabled={loading}>
            <option value="">Select a class...</option>
            {classes.map(cls => (
                <option key={cls.id} value={cls.id}>
                    {cls.name} - {cls.section}
                </option>
            ))}
        </select>
    );
}
```

---

## ⚡ Performance Features

### **Database Optimization**
- **Selective Fields**: Only fetches `id`, `name`, `section`, `grade_level`
- **Active Filter**: Automatically filters `is_active = true` classes only
- **Proper Indexing**: Uses database indexes for efficient querying
- **School Isolation**: Multi-tenant filtering for security and performance

### **Response Optimization**
- **Minimal Data**: No relationships loaded (teachers, students, etc.)
- **Consistent Ordering**: Sorted by name, section, id for stable pagination
- **Efficient Pagination**: Laravel's built-in pagination with metadata

### **Caching Considerations**
```php
// Optional: Cache frequently accessed data
Cache::remember("simple_classes_school_{$schoolId}", 300, function () {
    return $this->getSimpleClasses();
});
```

---

## 🛡️ Security & Multi-Tenancy

### **Access Control**
- **Module Check**: Requires `class-management` module access
- **Authentication**: Requires valid Bearer token
- **Role-based**: Available to admin, teacher, and other authorized roles

### **Data Isolation**
- **School Filtering**: Automatically filters by authenticated user's school
- **Active Records**: Only returns active classes (`is_active = true`)
- **Safe Search**: SQL injection prevention through parameterized queries

---

## 🔄 Comparison with Full Classes API

| Feature | Simple Classes API | Full Classes API |
|---------|-------------------|------------------|
| **Response Size** | ~50-100 bytes/record | ~500-1000 bytes/record |
| **Load Time** | ~50-100ms | ~200-500ms |
| **Memory Usage** | Low | Higher (relationships) |
| **Use Cases** | Dropdowns, selects | Detailed views, forms |
| **Relationships** | None | Teacher, students, subjects |
| **Fields** | 4 fields | 15+ fields |

---

## 🎯 Use Cases

### **Perfect For:**
- 📋 **Dropdown Lists**: Class selection in forms
- 🔍 **Search Autocomplete**: Quick class lookup
- 📱 **Mobile Apps**: Lightweight data for mobile interfaces  
- 🚀 **Quick Lookups**: When you only need basic class info
- 📊 **Reports**: Class references in reports and dashboards
- 🎨 **UI Components**: Badges, chips, quick references

### **Not Ideal For:**
- 📝 **Class Management**: Full CRUD operations (use main classes API)
- 👥 **Student Lists**: Getting class students (use `/classes/{id}/students`)
- 📅 **Timetables**: Class schedules (use `/classes/{id}/timetable`)
- 🎓 **Detailed Views**: Complete class information display

---

## 🧪 Testing Examples

### **Postman/Insomnia**
```json
{
    "method": "GET",
    "url": "{{base_url}}/api/classes/simple",
    "headers": {
        "Authorization": "Bearer {{token}}",
        "Accept": "application/json"
    },
    "query": {
        "search": "Grade 5",
        "per_page": "20",
        "page": "1"
    }
}
```

### **cURL Testing**
```bash
# Test basic functionality
curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     "http://localhost:8080/api/classes/simple"

# Test search
curl -H "Authorization: Bearer $TOKEN" \
     "http://localhost:8080/api/classes/simple?search=Primary"

# Test pagination limits
curl -H "Authorization: Bearer $TOKEN" \
     "http://localhost:8080/api/classes/simple?per_page=100"
```

---

## 📈 Expected Response Times

| Scenario | Expected Time | Database Queries |
|----------|---------------|------------------|
| **No Search** | 50-80ms | 1 query |
| **With Search** | 60-100ms | 1 query |
| **Large Dataset (1000+ classes)** | 80-120ms | 1 query |
| **Paginated (page 10+)** | 60-90ms | 1 query |

---

## 🔧 Technical Implementation

### **Database Query Example**
```sql
SELECT id, name, section, grade_level 
FROM classes 
WHERE school_id = ? 
  AND is_active = true 
  AND (name LIKE '%search%' OR section LIKE '%search%' OR grade_level LIKE '%search%')
ORDER BY name, section, id 
LIMIT 15 OFFSET 0;
```

### **Laravel Eloquent**
```php
$query = ClassRoom::select('id', 'name', 'section', 'grade_level')
    ->where('school_id', $schoolId)
    ->where('is_active', true)
    ->when($search, function ($q) use ($search) {
        $q->where(function ($subQ) use ($search) {
            $subQ->where('name', 'LIKE', "%{$search}%")
                 ->orWhere('section', 'LIKE', "%{$search}%")
                 ->orWhere('grade_level', 'LIKE', "%{$search}%");
        });
    })
    ->orderBy('name')
    ->orderBy('section')
    ->orderBy('id')
    ->paginate($perPage);
```

This Simple Classes API provides the perfect balance of functionality and performance for scenarios where you need quick access to class data without the overhead of full relationships and detailed information.
