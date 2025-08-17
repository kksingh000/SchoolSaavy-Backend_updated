# Classes API Pagination & Search Documentation

## GET /api/classes

Retrieve a paginated list of classes with optional filtering and search.

### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Current page number |
| `per_page` | integer | 15 | Number of items per page (1-100) |
| `search` | string | - | Search in class name, section, grade level, or teacher name/email |
| `grade_level` | string | - | Filter by grade level |
| `is_active` | boolean | - | Filter by active status |
| `class_teacher_id` | integer | - | Filter by class teacher ID |

### Example Requests

#### Basic pagination
```
GET /api/classes?page=1&per_page=20
```

#### With search and filters
```
GET /api/classes?search=Grade 5&grade_level=5&is_active=true&page=2&per_page=10
```

#### Search examples
```
GET /api/classes?search=John              # Search by teacher name
GET /api/classes?search=Grade 5           # Search by class name
GET /api/classes?search=A                 # Search by section
GET /api/classes?search=john@school.com   # Search by teacher email
```

### Search Functionality

The search parameter performs a comprehensive search across:
- **Class name** (e.g., "Grade 5", "Primary 1")
- **Section** (e.g., "A", "B", "Blue")
- **Grade level** (e.g., "5", "Primary")
- **Class teacher name** (e.g., "John Doe")
- **Class teacher email** (e.g., "teacher@school.com")

### Response Format

```json
{
    "status": "success",
    "message": "Classes retrieved successfully",
    "data": {
        "data": [
            {
                "id": 1,
                "name": "Grade 5",
                "section": "A",
                "grade_level": "5",
                "capacity": 30,
                "is_active": true,
                "created_at": "2024-01-15T10:30:00.000000Z",
                "updated_at": "2024-01-15T10:30:00.000000Z",
                "class_teacher": {
                    "id": 1,
                    "user": {
                        "id": 2,
                        "name": "John Doe",
                        "email": "john.doe@school.com"
                    }
                },
                "students": [
                    {
                        "id": 1,
                        "first_name": "Alice",
                        "last_name": "Johnson",
                        "admission_number": "ADM001",
                        "pivot": {
                            "roll_number": 1,
                            "enrolled_date": "2024-01-15",
                            "is_active": true
                        }
                    }
                ]
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 3,
            "per_page": 15,
            "total": 45,
            "from": 1,
            "to": 15,
            "has_more_pages": true,
            "prev_page_url": null,
            "next_page_url": "http://localhost:8080/api/classes?page=2"
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
| `total` | Total number of classes |
| `from` | Starting item number on current page |
| `to` | Ending item number on current page |
| `has_more_pages` | Whether there are more pages available |
| `prev_page_url` | URL for previous page (null if first page) |
| `next_page_url` | URL for next page (null if last page) |

### Frontend Integration Examples

#### JavaScript/Ajax
```javascript
async function fetchClasses(page = 1, perPage = 15, filters = {}) {
    const params = new URLSearchParams({
        page: page,
        per_page: perPage,
        ...filters
    });
    
    const response = await fetch(`/api/classes?${params}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
        }
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
        const classes = result.data.data;
        const pagination = result.data.pagination;
        
        updateClassList(classes);
        updatePaginationControls(pagination);
    }
}

// Usage examples
fetchClasses(1, 20); // First page, 20 items
fetchClasses(2, 15, { search: 'Grade 5', is_active: true }); // Second page with filters
```

#### React Component Example
```jsx
const ClassList = () => {
    const [classes, setClasses] = useState([]);
    const [pagination, setPagination] = useState({});
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [filters, setFilters] = useState({});

    const loadClasses = async (page = 1, perPage = 15) => {
        setLoading(true);
        try {
            const params = { 
                page, 
                per_page: perPage, 
                ...filters 
            };
            
            if (searchTerm) {
                params.search = searchTerm;
            }

            const response = await api.get('/classes', { params });
            
            setClasses(response.data.data.data);
            setPagination(response.data.data.pagination);
        } catch (error) {
            console.error('Failed to load classes:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSearch = (term) => {
        setSearchTerm(term);
        loadClasses(1); // Reset to first page when searching
    };

    return (
        <div>
            <SearchInput 
                value={searchTerm}
                onChange={handleSearch}
                placeholder="Search classes, teachers, or sections..."
            />
            <FilterControls filters={filters} onChange={setFilters} />
            <ClassTable classes={classes} loading={loading} />
            <Pagination 
                current={pagination.current_page}
                total={pagination.total}
                pageSize={pagination.per_page}
                onChange={(page, pageSize) => loadClasses(page, pageSize)}
            />
        </div>
    );
};
```

#### Vue.js Example
```vue
<template>
    <div>
        <input 
            v-model="searchTerm" 
            @input="debounceSearch"
            placeholder="Search classes..."
            class="search-input"
        />
        
        <div class="filters">
            <select v-model="filters.grade_level" @change="loadClasses(1)">
                <option value="">All Grades</option>
                <option value="1">Grade 1</option>
                <option value="2">Grade 2</option>
                <!-- More options -->
            </select>
            
            <select v-model="filters.is_active" @change="loadClasses(1)">
                <option value="">All Classes</option>
                <option value="true">Active Only</option>
                <option value="false">Inactive Only</option>
            </select>
        </div>
        
        <class-table :classes="classes" :loading="loading" />
        
        <pagination 
            :current="pagination.current_page"
            :total="pagination.total"
            :per-page="pagination.per_page"
            @change="loadClasses"
        />
    </div>
</template>

<script>
export default {
    data() {
        return {
            classes: [],
            pagination: {},
            loading: false,
            searchTerm: '',
            filters: {
                grade_level: '',
                is_active: ''
            }
        };
    },
    methods: {
        async loadClasses(page = 1, perPage = 15) {
            this.loading = true;
            try {
                const params = { page, per_page: perPage, ...this.filters };
                if (this.searchTerm) params.search = this.searchTerm;
                
                const response = await this.$api.get('/classes', { params });
                this.classes = response.data.data.data;
                this.pagination = response.data.data.pagination;
            } catch (error) {
                console.error('Failed to load classes:', error);
            } finally {
                this.loading = false;
            }
        },
        debounceSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadClasses(1);
            }, 300);
        }
    },
    mounted() {
        this.loadClasses();
    }
};
</script>
```

### Performance Optimizations

- **Eager Loading**: Related data (teacher, students) is loaded efficiently
- **Consistent Ordering**: Results are ordered by name, section, and ID for stable pagination
- **School Filtering**: All queries are automatically scoped to the user's school
- **Index Optimization**: Database queries use proper indexes for fast search
- **Relationship Optimization**: Only necessary fields are selected to reduce memory usage

### Error Handling

Common error scenarios:
```json
{
    "status": "error",
    "message": "Module not activated for your school. Please contact administration.",
    "code": "MODULE_ACCESS_DENIED"
}
```

### Advanced Search Tips

1. **Partial Matching**: Search terms use LIKE queries with wildcards
2. **Multi-field Search**: Single search term looks across multiple fields
3. **Case Insensitive**: All searches are case-insensitive
4. **Teacher Search**: Can search by teacher name or email
5. **Combined Filters**: Use search with other filters for precise results

### API Rate Limiting

- Default rate limit: 60 requests per minute
- Use pagination efficiently to reduce server load
- Consider caching frequently accessed pages on the frontend
