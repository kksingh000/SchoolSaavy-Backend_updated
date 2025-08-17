# Students API Pagination Documentation

## GET /api/students

Retrieve a paginated list of students with optional filtering.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Current page number |
| `per_page` | integer | 15 | Number of items per page (1-100) |
| `search` | string | - | Search in first name, last name, or admission number |
| `class_id` | integer | - | Filter by class ID |
| `gender` | string | - | Filter by gender (male/female/other) |
| `blood_group` | string | - | Filter by blood group |
| `is_active` | boolean | - | Filter by active status |
| `admission_date` | date | - | Filter by specific admission date (Y-m-d) |

### Example Requests

#### Basic pagination
```
GET /api/students?page=1&per_page=20
```

#### With search and filters
```
GET /api/students?search=John&class_id=5&gender=male&page=2&per_page=10
```

### Response Format

```json
{
    "status": "success",
    "message": "Students retrieved successfully",
    "data": {
        "data": [
            {
                "id": 1,
                "first_name": "John",
                "last_name": "Doe",
                "admission_number": "ADM001",
                "email": "john.doe@example.com",
                "gender": "male",
                "date_of_birth": "2010-05-15",
                "blood_group": "O+",
                "is_active": true,
                "profile_photo": "student-photos/photo.jpg",
                "admission_date": "2024-01-15",
                "created_at": "2024-01-15T10:30:00.000000Z",
                "updated_at": "2024-01-15T10:30:00.000000Z",
                "school": {
                    "id": 1,
                    "name": "ABC School",
                    "email": "info@abcschool.com"
                },
                "parents": [
                    {
                        "id": 1,
                        "first_name": "Robert",
                        "last_name": "Doe",
                        "email": "robert.doe@example.com",
                        "pivot": {
                            "relationship": "father",
                            "is_primary": true
                        }
                    }
                ]
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
            "next_page_url": "http://localhost:8080/api/students?page=2"
        }
    }
}
```

### Pagination Metadata

| Field | Description |
|-------|-------------|
| `current_page` | Current page number |
| `last_page` | Total number of pages |
| `per_page` | Items per page |
| `total` | Total number of items |
| `from` | Starting item number on current page |
| `to` | Ending item number on current page |
| `has_more_pages` | Whether there are more pages available |
| `prev_page_url` | URL for previous page (null if first page) |
| `next_page_url` | URL for next page (null if last page) |

### Frontend Integration Examples

#### JavaScript/Ajax
```javascript
async function fetchStudents(page = 1, perPage = 15, filters = {}) {
    const params = new URLSearchParams({
        page: page,
        per_page: perPage,
        ...filters
    });
    
    const response = await fetch(`/api/students?${params}`);
    const result = await response.json();
    
    if (result.status === 'success') {
        const students = result.data.data;
        const pagination = result.data.pagination;
        
        // Update your UI with students and pagination info
        updateStudentList(students);
        updatePaginationControls(pagination);
    }
}

// Usage examples
fetchStudents(1, 20); // First page, 20 items
fetchStudents(2, 15, { search: 'John', class_id: 5 }); // Second page with filters
```

#### React Component Example
```jsx
const StudentList = () => {
    const [students, setStudents] = useState([]);
    const [pagination, setPagination] = useState({});
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState({});

    const loadStudents = async (page = 1, perPage = 15) => {
        setLoading(true);
        try {
            const response = await api.get('/students', {
                params: { page, per_page: perPage, ...filters }
            });
            
            setStudents(response.data.data.data);
            setPagination(response.data.data.pagination);
        } catch (error) {
            console.error('Failed to load students:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <StudentTable students={students} loading={loading} />
            <Pagination 
                current={pagination.current_page}
                total={pagination.total}
                pageSize={pagination.per_page}
                onChange={(page, pageSize) => loadStudents(page, pageSize)}
            />
        </div>
    );
};
```

### Performance Notes

- Default page size is 15 items for optimal performance
- Maximum page size is limited to 100 items per request
- Results are ordered by first name, last name, and ID for consistent pagination
- Use search and filters to reduce dataset size for better performance
- Consider implementing client-side caching for frequently accessed pages

### Error Handling

If pagination parameters are invalid:
- `per_page` values outside 1-100 range are automatically clamped
- Invalid page numbers default to page 1
- Invalid filter values are ignored with a warning in logs
