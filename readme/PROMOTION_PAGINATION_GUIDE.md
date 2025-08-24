# Promotion API Pagination Implementation Guide

## 📋 **Overview**

The promotion system APIs have been enhanced with pagination support for better performance when dealing with large datasets. This guide explains how to implement and use pagination in the promotion system.

## 🔧 **Backend Implementation**

### **PromotionController Methods with Pagination**

#### **1. getCriteria() Method**
```php
<?php

namespace App\Http\Controllers;

use App\Services\PromotionService;
use App\Http\Requests\PromotionCriteriaRequest;
use Illuminate\Http\Request;

class PromotionController extends BaseController
{
    public function __construct(
        private PromotionService $promotionService
    ) {}

    /**
     * Get promotion criteria with pagination
     * 
     * @param Request $request
     * @param int $academicYearId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCriteria(Request $request, $academicYearId)
    {
        // Check module access
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            // Get pagination parameters with defaults
            $perPage = $request->get('per_page', 15); // Default 15 items per page
            $page = $request->get('page', 1);
            
            // Optional filters
            $filters = [
                'class_id' => $request->get('class_id'),
                'subject_id' => $request->get('subject_id'),
                'criteria_type' => $request->get('criteria_type'), // 'attendance', 'academic', 'behaviour'
                'status' => $request->get('status', 'active'), // 'active', 'inactive', 'all'
            ];

            // Get paginated criteria
            $criteria = $this->promotionService->getCriteriaPaginated($academicYearId, $perPage, $filters);

            return $this->successResponse($criteria, 'Promotion criteria retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve promotion criteria', null, 500);
        }
    }

    /**
     * Get student promotions with pagination
     */
    public function getStudentPromotions(Request $request, $academicYearId)
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            
            $filters = [
                'class_id' => $request->get('class_id'),
                'promotion_status' => $request->get('status'), // 'promoted', 'retained', 'review_required'
                'search' => $request->get('search'), // Search by student name/admission number
            ];

            $students = $this->promotionService->getStudentPromotionsPaginated($academicYearId, $perPage, $filters);

            return $this->successResponse($students, 'Student promotions retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve student promotions', null, 500);
        }
    }

    /**
     * Get promotion batches with pagination
     */
    public function getBatches(Request $request, $academicYearId)
    {
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }

        try {
            $perPage = $request->get('per_page', 10); // Smaller default for batches
            
            $filters = [
                'status' => $request->get('status'),
                'created_date_from' => $request->get('date_from'),
                'created_date_to' => $request->get('date_to'),
            ];

            $batches = $this->promotionService->getBatchesPaginated($academicYearId, $perPage, $filters);

            return $this->successResponse($batches, 'Promotion batches retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve promotion batches', null, 500);
        }
    }
}
```

#### **2. PromotionService Methods**
```php
<?php

namespace App\Services;

use App\Models\PromotionCriteria;
use App\Models\StudentPromotion;
use App\Models\PromotionBatch;

class PromotionService extends BaseService
{
    /**
     * Get paginated promotion criteria
     */
    public function getCriteriaPaginated($academicYearId, $perPage = 15, $filters = [])
    {
        $query = PromotionCriteria::where('academic_year_id', $academicYearId)
            ->where('school_id', auth()->user()->getSchool()->id)
            ->with(['class', 'subject', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['criteria_type'])) {
            $query->where('criteria_type', $filters['criteria_type']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get paginated student promotions
     */
    public function getStudentPromotionsPaginated($academicYearId, $perPage = 15, $filters = [])
    {
        $query = StudentPromotion::where('academic_year_id', $academicYearId)
            ->whereHas('student', function($q) {
                $q->where('school_id', auth()->user()->getSchool()->id);
            })
            ->with(['student', 'currentClass', 'promotedToClass', 'evaluatedBy'])
            ->orderBy('updated_at', 'desc');

        // Apply filters
        if (!empty($filters['class_id'])) {
            $query->where('current_class_id', $filters['class_id']);
        }

        if (!empty($filters['promotion_status'])) {
            $query->where('promotion_status', $filters['promotion_status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get paginated promotion batches
     */
    public function getBatchesPaginated($academicYearId, $perPage = 10, $filters = [])
    {
        $query = PromotionBatch::where('academic_year_id', $academicYearId)
            ->where('school_id', auth()->user()->getSchool()->id)
            ->with(['createdBy'])
            ->withCount(['studentPromotions'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['created_date_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_date_from']);
        }

        if (!empty($filters['created_date_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_date_to']);
        }

        return $query->paginate($perPage);
    }
}
```

## 🎨 **Frontend Implementation**

### **1. React/Vue.js Component with Pagination**

```javascript
// PromotionCriteriaComponent.jsx
import React, { useState, useEffect } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import Pagination from './components/Pagination';
import FilterForm from './components/FilterForm';

const PromotionCriteriaComponent = () => {
    const { academicYearId } = useParams();
    const [searchParams, setSearchParams] = useSearchParams();
    
    const [criteria, setCriteria] = useState([]);
    const [pagination, setPagination] = useState({
        current_page: 1,
        per_page: 15,
        total: 0,
        last_page: 1
    });
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState({
        class_id: '',
        subject_id: '',
        criteria_type: '',
        status: 'active'
    });

    const fetchCriteria = async (page = 1, currentFilters = filters) => {
        setLoading(true);
        try {
            const params = new URLSearchParams({
                page: page.toString(),
                per_page: pagination.per_page.toString(),
                ...currentFilters
            });

            const response = await fetch(`/api/promotions/criteria/${academicYearId}?${params}`);
            const result = await response.json();

            if (result.status === 'success') {
                setCriteria(result.data.data);
                setPagination({
                    current_page: result.data.current_page,
                    per_page: result.data.per_page,
                    total: result.data.total,
                    last_page: result.data.last_page,
                    from: result.data.from,
                    to: result.data.to
                });

                // Update URL parameters
                setSearchParams({ page: page.toString(), ...currentFilters });
            }
        } catch (error) {
            console.error('Failed to fetch criteria:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const page = parseInt(searchParams.get('page')) || 1;
        const urlFilters = {
            class_id: searchParams.get('class_id') || '',
            subject_id: searchParams.get('subject_id') || '',
            criteria_type: searchParams.get('criteria_type') || '',
            status: searchParams.get('status') || 'active'
        };
        setFilters(urlFilters);
        fetchCriteria(page, urlFilters);
    }, [academicYearId]);

    const handlePageChange = (page) => {
        fetchCriteria(page);
    };

    const handleFilterChange = (newFilters) => {
        setFilters(newFilters);
        fetchCriteria(1, newFilters); // Reset to page 1 when filtering
    };

    const handlePerPageChange = (perPage) => {
        setPagination(prev => ({ ...prev, per_page: perPage }));
        fetchCriteria(1); // Reset to page 1 when changing per page
    };

    return (
        <div className="promotion-criteria-container">
            <div className="header">
                <h2>Promotion Criteria</h2>
                <div className="header-actions">
                    <button className="btn-primary" onClick={() => setShowCreateModal(true)}>
                        Add New Criteria
                    </button>
                </div>
            </div>

            {/* Filters */}
            <FilterForm 
                filters={filters}
                onChange={handleFilterChange}
                academicYearId={academicYearId}
            />

            {/* Criteria Table */}
            <div className="criteria-table-container">
                {loading ? (
                    <div className="loading-spinner">Loading...</div>
                ) : (
                    <>
                        <table className="criteria-table">
                            <thead>
                                <tr>
                                    <th>Criteria Type</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Min. Value</th>
                                    <th>Weight (%)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {criteria.map(criterion => (
                                    <tr key={criterion.id}>
                                        <td className={`criteria-type ${criterion.criteria_type}`}>
                                            {criterion.criteria_type}
                                        </td>
                                        <td>{criterion.class?.name || 'All Classes'}</td>
                                        <td>{criterion.subject?.name || 'All Subjects'}</td>
                                        <td>{criterion.minimum_value}%</td>
                                        <td>{criterion.weight_percentage}%</td>
                                        <td>
                                            <span className={`status ${criterion.is_active ? 'active' : 'inactive'}`}>
                                                {criterion.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td>
                                            <button onClick={() => editCriteria(criterion.id)}>Edit</button>
                                            <button onClick={() => deleteCriteria(criterion.id)}>Delete</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {/* Pagination Info */}
                        <div className="pagination-info">
                            Showing {pagination.from} to {pagination.to} of {pagination.total} criteria
                        </div>

                        {/* Pagination Controls */}
                        <Pagination
                            currentPage={pagination.current_page}
                            lastPage={pagination.last_page}
                            total={pagination.total}
                            perPage={pagination.per_page}
                            onPageChange={handlePageChange}
                            onPerPageChange={handlePerPageChange}
                        />
                    </>
                )}
            </div>
        </div>
    );
};

export default PromotionCriteriaComponent;
```

### **2. Reusable Pagination Component**

```javascript
// components/Pagination.jsx
import React from 'react';

const Pagination = ({ 
    currentPage, 
    lastPage, 
    total, 
    perPage, 
    onPageChange, 
    onPerPageChange 
}) => {
    const generatePageNumbers = () => {
        const pages = [];
        const maxVisible = 7;
        
        if (lastPage <= maxVisible) {
            for (let i = 1; i <= lastPage; i++) {
                pages.push(i);
            }
        } else {
            if (currentPage <= 4) {
                for (let i = 1; i <= 5; i++) pages.push(i);
                pages.push('...');
                pages.push(lastPage);
            } else if (currentPage >= lastPage - 3) {
                pages.push(1);
                pages.push('...');
                for (let i = lastPage - 4; i <= lastPage; i++) pages.push(i);
            } else {
                pages.push(1);
                pages.push('...');
                for (let i = currentPage - 1; i <= currentPage + 1; i++) pages.push(i);
                pages.push('...');
                pages.push(lastPage);
            }
        }
        
        return pages;
    };

    return (
        <div className="pagination-container">
            <div className="pagination-controls">
                {/* Per Page Selector */}
                <div className="per-page-selector">
                    <label>Show:</label>
                    <select value={perPage} onChange={(e) => onPerPageChange(parseInt(e.target.value))}>
                        <option value={10}>10</option>
                        <option value={15}>15</option>
                        <option value={25}>25</option>
                        <option value={50}>50</option>
                        <option value={100}>100</option>
                    </select>
                    <span>per page</span>
                </div>

                {/* Page Navigation */}
                <div className="page-navigation">
                    <button
                        onClick={() => onPageChange(currentPage - 1)}
                        disabled={currentPage === 1}
                        className="page-btn prev"
                    >
                        Previous
                    </button>

                    {generatePageNumbers().map((page, index) => (
                        <React.Fragment key={index}>
                            {page === '...' ? (
                                <span className="page-ellipsis">...</span>
                            ) : (
                                <button
                                    onClick={() => onPageChange(page)}
                                    className={`page-btn ${currentPage === page ? 'active' : ''}`}
                                >
                                    {page}
                                </button>
                            )}
                        </React.Fragment>
                    ))}

                    <button
                        onClick={() => onPageChange(currentPage + 1)}
                        disabled={currentPage === lastPage}
                        className="page-btn next"
                    >
                        Next
                    </button>
                </div>

                {/* Page Info */}
                <div className="pagination-info">
                    Page {currentPage} of {lastPage} ({total} total items)
                </div>
            </div>
        </div>
    );
};

export default Pagination;
```

### **3. Filter Component**

```javascript
// components/FilterForm.jsx
import React, { useState, useEffect } from 'react';

const FilterForm = ({ filters, onChange, academicYearId }) => {
    const [classes, setClasses] = useState([]);
    const [subjects, setSubjects] = useState([]);

    useEffect(() => {
        // Fetch classes and subjects for filter dropdowns
        fetchClasses();
        fetchSubjects();
    }, [academicYearId]);

    const fetchClasses = async () => {
        try {
            const response = await fetch('/api/classes/simple');
            const result = await response.json();
            if (result.status === 'success') {
                setClasses(result.data);
            }
        } catch (error) {
            console.error('Failed to fetch classes:', error);
        }
    };

    const fetchSubjects = async () => {
        try {
            const response = await fetch('/api/subjects');
            const result = await response.json();
            if (result.status === 'success') {
                setSubjects(result.data.data);
            }
        } catch (error) {
            console.error('Failed to fetch subjects:', error);
        }
    };

    const handleFilterChange = (key, value) => {
        const newFilters = { ...filters, [key]: value };
        onChange(newFilters);
    };

    const clearFilters = () => {
        const clearedFilters = {
            class_id: '',
            subject_id: '',
            criteria_type: '',
            status: 'active'
        };
        onChange(clearedFilters);
    };

    return (
        <div className="filter-form">
            <div className="filter-row">
                <div className="filter-group">
                    <label>Class:</label>
                    <select
                        value={filters.class_id}
                        onChange={(e) => handleFilterChange('class_id', e.target.value)}
                    >
                        <option value="">All Classes</option>
                        {classes.map(cls => (
                            <option key={cls.id} value={cls.id}>
                                {cls.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="filter-group">
                    <label>Subject:</label>
                    <select
                        value={filters.subject_id}
                        onChange={(e) => handleFilterChange('subject_id', e.target.value)}
                    >
                        <option value="">All Subjects</option>
                        {subjects.map(subject => (
                            <option key={subject.id} value={subject.id}>
                                {subject.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="filter-group">
                    <label>Criteria Type:</label>
                    <select
                        value={filters.criteria_type}
                        onChange={(e) => handleFilterChange('criteria_type', e.target.value)}
                    >
                        <option value="">All Types</option>
                        <option value="attendance">Attendance</option>
                        <option value="academic">Academic</option>
                        <option value="behaviour">Behaviour</option>
                        <option value="assignment">Assignment</option>
                        <option value="assessment">Assessment</option>
                    </select>
                </div>

                <div className="filter-group">
                    <label>Status:</label>
                    <select
                        value={filters.status}
                        onChange={(e) => handleFilterChange('status', e.target.value)}
                    >
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div className="filter-actions">
                    <button onClick={clearFilters} className="btn-secondary">
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>
    );
};

export default FilterForm;
```

## 📊 **API Usage Examples**

### **1. Basic Pagination**
```bash
# Get first page with default 15 items per page
GET /api/promotions/criteria/123

# Get specific page with custom per_page
GET /api/promotions/criteria/123?page=2&per_page=25
```

### **2. With Filters**
```bash
# Filter by class and status
GET /api/promotions/criteria/123?class_id=5&status=active&page=1&per_page=15

# Filter by criteria type
GET /api/promotions/criteria/123?criteria_type=attendance&page=1

# Search students by name
GET /api/promotions/students/123?search=john&page=1&per_page=10
```

### **3. API Response Format**
```json
{
  "status": "success",
  "message": "Promotion criteria retrieved successfully",
  "data": {
    "data": [
      {
        "id": 1,
        "criteria_type": "attendance",
        "class_id": 5,
        "subject_id": null,
        "minimum_value": 75.0,
        "weight_percentage": 30.0,
        "is_active": true,
        "class": {
          "id": 5,
          "name": "Grade 10A"
        },
        "subject": null,
        "created_by": {
          "id": 1,
          "name": "Admin User"
        }
      }
    ],
    "current_page": 1,
    "per_page": 15,
    "total": 48,
    "last_page": 4,
    "from": 1,
    "to": 15,
    "prev_page_url": null,
    "next_page_url": "http://localhost/api/promotions/criteria/123?page=2",
    "path": "http://localhost/api/promotions/criteria/123"
  }
}
```

## 🎯 **Benefits of Pagination Implementation**

1. **Performance**: Faster loading times for large datasets
2. **User Experience**: Better navigation through large lists
3. **Server Resources**: Reduced memory usage and database load
4. **Caching**: More effective caching with smaller result sets
5. **Mobile Friendly**: Better experience on mobile devices
6. **SEO**: URL-based pagination supports bookmarking and sharing

This pagination implementation provides a robust foundation for handling large promotion datasets while maintaining excellent user experience and system performance! 📈
