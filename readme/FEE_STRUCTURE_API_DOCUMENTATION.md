# Fee Structure API Documentation

## Overview
The Fee Structure API provides comprehensive management of school fee structures, including creation, updating, deletion, and reporting capabilities. All endpoints require authentication and proper module access.

## Authentication & Authorization
- **Required**: `Authorization: Bearer {token}`
- **Module**: `fee-management` must be active for the school
- **Middleware**: `auth:sanctum`, `school.status`, `inject.school`

## Base URL
```
/api/fee-structures
```

---

## API Endpoints

### 1. Get All Fee Structures
**GET** `/fee-structures`

Retrieve all fee structures for the authenticated user's school with pagination and filtering.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `class_id` | integer | No | Filter by specific class |
| `is_active` | boolean | No | Filter by active status (default: true) |
| `search` | string | No | Search in name and description |
| `page` | integer | No | Page number for pagination (default: 1) |
| `per_page` | integer | No | Items per page (default: 15) |

#### Response
```json
{
    "status": "success",
    "message": "Fee structures retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "school_id": 1,
                "name": "Annual Fees - Grade 5A",
                "class_id": 5,
                "academic_year_id": 1,
                "academic_year": "2024-25",
                "fee_components": [
                    {
                        "type": "tuition",
                        "name": "Tuition Fee",
                        "amount": 10000,
                        "due_date": "2024-05-01",
                        "is_mandatory": true,
                        "description": "Monthly tuition fee"
                    }
                ],
                "total_amount": 16500.00,
                "is_active": true,
                "description": "Complete annual fee structure",
                "created_at": "2024-08-25T15:30:00.000000Z",
                "updated_at": "2024-08-25T15:30:00.000000Z",
                "school": {
                    "id": 1,
                    "name": "ABC School"
                },
                "academic_year": {
                    "id": 1,
                    "year_label": "2024-25",
                    "display_name": "Academic Year 2024-25"
                },
                "class": {
                    "id": 5,
                    "name": "Grade 5A",
                    "grade_level": 5
                }
            }
        ],
        "per_page": 15,
        "total": 25
    }
}
```

---

### 2. Get Fee Structure by ID
**GET** `/fee-structures/{id}`

Retrieve a specific fee structure with detailed information.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Fee structure ID |

#### Response
```json
{
    "status": "success",
    "message": "Fee structure retrieved successfully",
    "data": {
        "id": 1,
        "school_id": 1,
        "name": "Annual Fees - Grade 5A",
        "class_id": 5,
        "academic_year_id": 1,
        "fee_components": [
            {
                "type": "tuition",
                "name": "Tuition Fee",
                "amount": 10000,
                "due_date": "2024-05-01",
                "is_mandatory": true,
                "description": "Monthly tuition fee"
            },
            {
                "type": "development",
                "name": "Development Fee",
                "amount": 2000,
                "due_date": "2024-04-15",
                "is_mandatory": true,
                "description": "Annual development fee"
            }
        ],
        "total_amount": 16500.00,
        "is_active": true,
        "description": "Complete annual fee structure",
        "student_fees": [
            {
                "id": 1,
                "student_id": 15,
                "student": {
                    "id": 15,
                    "first_name": "John",
                    "last_name": "Doe",
                    "admission_number": "2024001"
                },
                "amount": 10000.00,
                "status": "pending",
                "payments": []
            }
        ]
    }
}
```

---

### 3. Create Fee Structure
**POST** `/fee-structures`

Create a new fee structure for the school.

#### Request Body
```json
{
    "name": "Annual Fees - Grade 6A",
    "class_id": 6,
    "fee_components": [
        {
            "type": "tuition",
            "name": "Tuition Fee",
            "amount": 12000,
            "due_date": "2024-05-01",
            "is_mandatory": true,
            "description": "Monthly tuition fee for Grade 6"
        },
        {
            "type": "development",
            "name": "Development Fee",
            "amount": 2500,
            "due_date": "2024-04-15",
            "is_mandatory": true,
            "description": "Annual development and infrastructure fee"
        }
    ],
    "total_amount": 14500,
    "is_active": true,
    "description": "Annual fee structure for Grade 6A"
}
```

#### Validation Rules
| Field | Rules | Description |
|-------|-------|-------------|
| `name` | required, string, max:255, unique per school | Fee structure name |
| `class_id` | nullable, exists:classes,id | Associated class (optional) |
| `fee_components` | required, array, min:1 | Array of fee components |
| `fee_components.*.type` | required, string, max:100 | Component type |
| `fee_components.*.name` | required, string, max:255 | Component name |
| `fee_components.*.amount` | required, numeric, min:0, max:999999.99 | Component amount |
| `fee_components.*.due_date` | nullable, date, after_or_equal:today | Due date |
| `fee_components.*.is_mandatory` | boolean | Whether component is mandatory |
| `total_amount` | required, numeric, must equal sum of components | Total amount |

#### Response
```json
{
    "status": "success",
    "message": "Fee structure created successfully",
    "data": {
        "id": 2,
        "school_id": 1,
        "name": "Annual Fees - Grade 6A",
        "class_id": 6,
        "academic_year_id": 1,
        "fee_components": [...],
        "total_amount": 14500.00,
        "is_active": true,
        "created_at": "2024-08-25T16:00:00.000000Z"
    }
}
```

---

### 4. Update Fee Structure
**PUT** `/fee-structures/{id}`

Update an existing fee structure.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Fee structure ID |

#### Request Body
Same as create, but all fields are optional for updates.

#### Response
```json
{
    "status": "success",
    "message": "Fee structure updated successfully",
    "data": {
        "id": 1,
        "name": "Updated Fee Structure Name",
        // ... updated data
    }
}
```

---

### 5. Delete Fee Structure
**DELETE** `/fee-structures/{id}`

Soft delete a fee structure. Cannot delete if there are existing payments.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Fee structure ID |

#### Response
```json
{
    "status": "success",
    "message": "Fee structure deleted successfully",
    "data": null
}
```

#### Error Response (if payments exist)
```json
{
    "status": "error",
    "message": "Cannot delete fee structure with existing payments",
    "errors": null
}
```

---

### 6. Toggle Fee Structure Status
**PATCH** `/fee-structures/{id}/toggle-status`

Toggle the active status of a fee structure.

#### Response
```json
{
    "status": "success",
    "message": "Fee structure status updated successfully",
    "data": {
        "id": 1,
        "is_active": false,
        // ... other data
    }
}
```

---

### 7. Generate Student Fees
**POST** `/fee-structures/{id}/generate-student-fees`

Generate individual student fee records for all students in the associated class.

#### Response
```json
{
    "status": "success",
    "message": "Student fees generated successfully",
    "data": {
        "fee_structure_id": 1,
        "students_count": 30,
        "fees_generated": 90,
        "components_per_student": 3
    }
}
```

---

### 8. Get Fee Structure Statistics
**GET** `/fee-structures/{id}/statistics`

Get detailed statistics for a fee structure including payment status.

#### Response
```json
{
    "status": "success",
    "message": "Fee structure statistics retrieved successfully",
    "data": {
        "fee_structure": {
            "id": 1,
            "name": "Annual Fees - Grade 5A",
            "total_amount": 16500.00
        },
        "total_students": 28,
        "total_fees_generated": 84,
        "total_amount_due": 462000.00,
        "total_amount_paid": 231000.00,
        "total_amount_pending": 231000.00,
        "payment_statistics": {
            "paid_count": 42,
            "partial_count": 15,
            "pending_count": 27,
            "overdue_count": 12
        },
        "collection_rate": 50.00
    }
}
```

---

### 9. Get Fee Structures by Class
**GET** `/fee-structures/class/{classId}`

Get all active fee structures for a specific class.

#### Path Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `classId` | integer | Yes | Class ID |

#### Response
```json
{
    "status": "success",
    "message": "Class fee structures retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "name": "Annual Fees - Grade 5A",
                // ... fee structure data
            }
        ]
    }
}
```

---

### 10. Clone Fee Structure
**POST** `/fee-structures/{id}/clone`

Clone an existing fee structure to a new academic year or class.

#### Request Body
```json
{
    "target_academic_year_id": 2,
    "target_class_id": 7,
    "name_suffix": " (Copy 2025)"
}
```

#### Request Parameters
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `target_academic_year_id` | integer | No | Target academic year |
| `target_class_id` | integer | No | Target class |
| `name_suffix` | string | No | Suffix for new name (default: " (Copy)") |

#### Response
```json
{
    "status": "success",
    "message": "Fee structure cloned successfully",
    "data": {
        "id": 3,
        "name": "Annual Fees - Grade 5A (Copy 2025)",
        "academic_year_id": 2,
        "class_id": 7,
        // ... cloned data
    }
}
```

---

## Error Responses

### Validation Error (422)
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "name": ["Fee structure name is required"],
        "total_amount": ["Total amount must equal the sum of all fee component amounts"]
    }
}
```

### Module Access Denied (403)
```json
{
    "status": "error",
    "message": "Module not activated for your school. Please contact administration.",
    "code": "MODULE_ACCESS_DENIED"
}
```

### Not Found (404)
```json
{
    "status": "error",
    "message": "Fee structure not found",
    "errors": null
}
```

### Server Error (500)
```json
{
    "status": "error",
    "message": "Failed to create fee structure",
    "errors": null
}
```

---

## Usage Examples

### Creating a Multi-Component Fee Structure
```javascript
const feeStructureData = {
    name: "Complete Annual Package - Grade 8",
    class_id: 12,
    fee_components: [
        {
            type: "tuition",
            name: "Monthly Tuition",
            amount: 15000,
            due_date: "2024-05-01",
            is_mandatory: true,
            description: "Regular monthly tuition fee"
        },
        {
            type: "development",
            name: "Infrastructure Development",
            amount: 3000,
            due_date: "2024-04-15",
            is_mandatory: true,
            description: "Annual infrastructure development fee"
        },
        {
            type: "activity",
            name: "Sports & Activities",
            amount: 2000,
            due_date: "2024-05-15",
            is_mandatory: false,
            description: "Optional sports and extracurricular activities"
        },
        {
            type: "transport",
            name: "School Transport",
            amount: 4000,
            due_date: "2024-05-01",
            is_mandatory: false,
            description: "Optional school bus service"
        }
    ],
    total_amount: 24000,
    is_active: true,
    description: "Comprehensive fee package including all optional services"
};

// POST /api/fee-structures
const response = await fetch('/api/fee-structures', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer your-token',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify(feeStructureData)
});
```

### Filtering and Searching Fee Structures
```javascript
// GET fee structures with filters
const params = new URLSearchParams({
    class_id: 5,
    is_active: 'true',
    search: 'tuition',
    page: 1,
    per_page: 10
});

const response = await fetch(`/api/fee-structures?${params}`, {
    headers: {
        'Authorization': 'Bearer your-token',
        'Accept': 'application/json'
    }
});
```

---

## Notes

1. **Academic Year Integration**: All fee structures are automatically associated with the current academic year via middleware.

2. **School Isolation**: All queries are automatically filtered by school_id to ensure data isolation.

3. **Caching**: Read operations are cached for 5 minutes to improve performance.

4. **Student Fee Generation**: When a fee structure is created with a class_id, individual StudentFee records are automatically created for all active students in that class.

5. **Payment Integration**: The API integrates with the payment system to track fee collection status.

6. **Validation**: Comprehensive validation ensures data integrity, including verification that total_amount equals the sum of component amounts.

7. **Soft Deletes**: Fee structures use soft deletes to maintain historical data integrity.
