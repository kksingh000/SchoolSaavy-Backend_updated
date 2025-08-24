# 📊 Academic Years API with Pagination & Search - Documentation

## 🎯 **Enhanced Academic Years Endpoint**

The existing `GET /api/admin/academic-years` endpoint now supports **pagination**, **search**, and **advanced filtering** without requiring separate APIs.

## 📡 **API Usage**

### **Basic Request (Paginated by default)**
```http
GET /api/admin/academic-years
Authorization: Bearer {token}
```

**Default Response:**
```json
{
  "status": "success",
  "message": "Academic years retrieved successfully",
  "data": {
    "current_page": 1,
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
          "days_remaining": 161
        },
        "statistics": {
          "total_students": 450,
          "promoted": 0,
          "conditionally_promoted": 0,
          "failed": 0,
          "pending": 0
        },
        "criteria_count": 15,
        "created_at": "2024-08-01T10:00:00.000000Z",
        "updated_at": "2024-08-24T14:30:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost:8080/api/admin/academic-years?page=1",
    "from": 1,
    "last_page": 3,
    "last_page_url": "http://localhost:8080/api/admin/academic-years?page=3",
    "links": [...],
    "next_page_url": "http://localhost:8080/api/admin/academic-years?page=2",
    "path": "http://localhost:8080/api/admin/academic-years",
    "per_page": 10,
    "prev_page_url": null,
    "to": 10,
    "total": 25
  }
}
```

## 🔍 **Search & Filter Parameters**

### **1. Pagination Controls**
```http
GET /api/admin/academic-years?page=2&per_page=5
```
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 10)

### **2. Search Filter**
```http
GET /api/admin/academic-years?search=2024
```
- Searches in `year_label` and `display_name`
- Case-insensitive partial matching

### **3. Status Filter**
```http
GET /api/admin/academic-years?status=active
```
Available values:
- `active` - Currently active academic years
- `upcoming` - Future academic years
- `promotion_period` - Years in promotion phase
- `completed` - Finished academic years
- `all` - No status filter (default)

### **4. Current Year Filter**
```http
GET /api/admin/academic-years?is_current=1
```
- `is_current=1` - Only current academic year
- `is_current=0` - Only non-current academic years

### **5. Date Range Filter**
```http
GET /api/admin/academic-years?year_from=2020&year_to=2025
```
- `year_from` - Academic years starting from this year
- `year_to` - Academic years ending before this year

### **6. Promotion Status Filter**
```http
GET /api/admin/academic-years?promotion_status=active
```
Available values:
- `active` - Currently in promotion period
- `upcoming` - Promotion period scheduled for future
- `completed` - Promotion period finished

### **7. Sorting Options**
```http
GET /api/admin/academic-years?sort_by=start_date&sort_direction=asc
```
Available sort fields:
- `start_date` (default)
- `end_date`
- `year_label`
- `status`
- `created_at`

Sort directions:
- `desc` (default)
- `asc`

## 🎯 **Combined Filter Examples**

### **Example 1: Search Active Years**
```http
GET /api/admin/academic-years?search=2024&status=active&per_page=5
```

### **Example 2: Current Year with Promotion Info**
```http
GET /api/admin/academic-years?is_current=1&promotion_status=active
```

### **Example 3: Recent Years, Sorted by Creation**
```http
GET /api/admin/academic-years?year_from=2020&sort_by=created_at&sort_direction=desc&per_page=15
```

### **Example 4: Comprehensive Search**
```http
GET /api/admin/academic-years?search=Academic&status=completed&year_from=2020&year_to=2023&sort_by=end_date&sort_direction=asc&page=1&per_page=8
```

## 🔄 **Frontend Integration**

### **React/Vue Example**
```javascript
const useAcademicYears = (filters = {}) => {
  const [academicYears, setAcademicYears] = useState(null);
  const [loading, setLoading] = useState(false);
  const [pagination, setPagination] = useState({});

  const fetchAcademicYears = async (page = 1) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page,
        per_page: 10,
        ...filters
      });

      const response = await api.get(`/admin/academic-years?${params}`);
      setAcademicYears(response.data.data);
      setPagination({
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        total: response.data.total,
        per_page: response.data.per_page
      });
    } catch (error) {
      console.error('Failed to fetch academic years:', error);
    } finally {
      setLoading(false);
    }
  };

  return { academicYears, pagination, loading, fetchAcademicYears };
};

// Usage in component
const AcademicYearsPage = () => {
  const [filters, setFilters] = useState({
    search: '',
    status: 'all',
    sort_by: 'start_date',
    sort_direction: 'desc'
  });

  const { academicYears, pagination, loading, fetchAcademicYears } = 
    useAcademicYears(filters);

  const handleSearch = (searchTerm) => {
    setFilters(prev => ({ ...prev, search: searchTerm }));
    fetchAcademicYears(1); // Reset to page 1
  };

  const handlePageChange = (page) => {
    fetchAcademicYears(page);
  };

  return (
    <div>
      <SearchBox onSearch={handleSearch} />
      <FilterDropdown 
        value={filters.status}
        onChange={(status) => setFilters(prev => ({ ...prev, status }))}
      />
      
      {loading ? (
        <Loader />
      ) : (
        <>
          <AcademicYearsList data={academicYears} />
          <Pagination 
            currentPage={pagination.current_page}
            totalPages={pagination.last_page}
            onPageChange={handlePageChange}
          />
        </>
      )}
    </div>
  );
};
```

### **Query Builder Helper**
```javascript
const buildAcademicYearQuery = (filters) => {
  const params = new URLSearchParams();
  
  // Add non-empty filters
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== null && value !== '' && value !== undefined) {
      params.append(key, value);
    }
  });
  
  return params.toString();
};

// Usage
const query = buildAcademicYearQuery({
  search: 'Academic',
  status: 'active',
  per_page: 15,
  page: 2
});

const url = `/admin/academic-years?${query}`;
```

## 📊 **Response Structure Details**

### **Pagination Meta Data**
```json
{
  "current_page": 1,
  "first_page_url": "...",
  "from": 1,
  "last_page": 5,
  "last_page_url": "...",
  "next_page_url": "...",
  "path": "...",
  "per_page": 10,
  "prev_page_url": null,
  "to": 10,
  "total": 42
}
```

### **Academic Year Data Structure**
```json
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
    "days_remaining": 161
  },
  "statistics": {
    "total_students": 450,
    "promoted": 0,
    "conditionally_promoted": 0,
    "failed": 0,
    "pending": 0,
    "graduated": 0,
    "transferred": 0,
    "withdrawn": 0
  },
  "criteria_count": 15,
  "created_at": "2024-08-01T10:00:00.000000Z",
  "updated_at": "2024-08-24T14:30:00.000000Z"
}
```

## 🎯 **Performance Optimizations**

### **Built-in Optimizations**
1. **Eager Loading** - Loads relationships in single query
2. **Indexed Searching** - Database indexes on searchable fields  
3. **Efficient Filtering** - Query-level filtering before data retrieval
4. **Selective Columns** - Only loads necessary data
5. **Laravel Pagination** - Native database-level pagination

### **Best Practices**
```javascript
// ✅ Good - Use reasonable page sizes
const response = await api.get('/admin/academic-years?per_page=10');

// ✅ Good - Combine filters to reduce results
const response = await api.get('/admin/academic-years?status=active&is_current=1');

// ❌ Avoid - Very large page sizes
const response = await api.get('/admin/academic-years?per_page=1000');

// ❌ Avoid - Unnecessary requests without filters
const response = await api.get('/admin/academic-years?page=1&per_page=100');
```

## 🚀 **Summary**

The enhanced Academic Years API provides:

1. **✅ Native Laravel Pagination** - Efficient database-level pagination
2. **🔍 Comprehensive Search** - Text search across multiple fields
3. **🎛️ Advanced Filtering** - Status, dates, promotion period filters
4. **📊 Flexible Sorting** - Multiple sort options with directions
5. **⚡ High Performance** - Optimized queries with eager loading
6. **🔄 Frontend Ready** - Standard pagination response format

**No separate API needed - everything works through the existing `/api/admin/academic-years` endpoint!** 🎯✨
