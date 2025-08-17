# Parent-Student Management API Documentation

## 📋 Overview

The Parent-Student Management API provides comprehensive functionality to manage relationships between parents and students in the SchoolSavvy platform. These APIs allow administrators and teachers to create parent accounts, assign existing parents to students, manage relationships, and handle bulk operations.

---

## 🔐 Authentication & Authorization

### **Required Authentication:**
- **Bearer Token**: All endpoints require valid authentication
- **Module Access**: Requires `student-management` module access
- **User Types**: Available to `admin` and authorized `teacher` roles

### **Multi-Tenant Security:**
- All operations are automatically filtered by school_id
- Parents and students must belong to the authenticated user's school

---

## 📡 API Endpoints

### **1. Get Student's Parents**

**GET** `/api/students/{studentId}/parents`

Get all parents assigned to a specific student.

#### Request Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `studentId` | integer | ✅ | ID of the student |

#### Response Example:
```json
{
    "status": "success",
    "message": "Student parents retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "John Smith",
            "email": "john.smith@example.com",
            "phone": "+1234567890",
            "occupation": "Engineer",
            "relationship": "father",
            "is_primary": true,
            "assigned_at": "2025-08-15 10:30:00"
        },
        {
            "id": 2,
            "name": "Jane Smith",
            "email": "jane.smith@example.com",
            "phone": "+1234567891",
            "occupation": "Teacher",
            "relationship": "mother",
            "is_primary": false,
            "assigned_at": "2025-08-15 10:35:00"
        }
    ]
}
```

---

### **2. Assign Existing Parent to Student**

**POST** `/api/students/{studentId}/parents/assign`

Assign an existing parent account to a student.

#### Request Parameters:
| Parameter | Type | Required | Options | Description |
|-----------|------|----------|---------|-------------|
| `studentId` | integer | ✅ | - | ID of the student |
| `parent_id` | integer | ✅ | - | ID of the existing parent |
| `relationship` | string | ✅ | father, mother, guardian | Relationship type |
| `is_primary` | boolean | ❌ | true, false | Is this the primary contact (default: false) |

#### Request Body Example:
```json
{
    "parent_id": 5,
    "relationship": "father",
    "is_primary": true
}
```

#### Response Example:
```json
{
    "status": "success",
    "message": "Parent assigned to student successfully",
    "data": {
        "student_id": 123,
        "parent_id": 5,
        "parent_name": "David Johnson",
        "parent_email": "david.johnson@example.com",
        "relationship": "father",
        "is_primary": true,
        "assigned_at": "2025-08-17 14:20:00"
    }
}
```

---

### **3. Create New Parent and Assign to Student**

**POST** `/api/students/{studentId}/parents/create`

Create a new parent account and immediately assign them to a student.

#### Request Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `studentId` | integer | ✅ | ID of the student |
| `name` | string | ✅ | Full name of the parent |
| `email` | string | ✅ | Unique email address |
| `password` | string | ✅ | Password (min 6 characters) |
| `phone` | string | ✅ | Primary phone number |
| `alternate_phone` | string | ❌ | Secondary phone number |
| `gender` | string | ❌ | male, female, other |
| `occupation` | string | ❌ | Parent's occupation |
| `address` | string | ❌ | Home address |
| `relationship` | string | ✅ | father, mother, guardian |
| `is_primary` | boolean | ❌ | Primary contact (default: false) |

#### Request Body Example:
```json
{
    "name": "Michael Brown",
    "email": "michael.brown@example.com",
    "password": "securePassword123",
    "phone": "+1234567892",
    "alternate_phone": "+1234567893",
    "gender": "male",
    "occupation": "Doctor",
    "address": "123 Main St, City, State",
    "relationship": "father",
    "is_primary": true
}
```

#### Response Example:
```json
{
    "status": "success",
    "message": "Parent created and assigned successfully",
    "data": {
        "parent": {
            "id": 15,
            "name": "Michael Brown",
            "email": "michael.brown@example.com",
            "phone": "+1234567892",
            "occupation": "Doctor",
            "relationship": "father",
            "is_primary": true
        },
        "student": {
            "id": 123,
            "name": "Emily Brown",
            "admission_number": "STU123"
        },
        "assigned_at": "2025-08-17 14:25:00"
    }
}
```

---

### **4. Update Parent-Student Relationship**

**PUT** `/api/students/{studentId}/parents/{parentId}`

Update the relationship between a parent and student.

#### Request Parameters:
| Parameter | Type | Required | Options | Description |
|-----------|------|----------|---------|-------------|
| `studentId` | integer | ✅ | - | ID of the student |
| `parentId` | integer | ✅ | - | ID of the parent |
| `relationship` | string | ❌ | father, mother, guardian | New relationship type |
| `is_primary` | boolean | ❌ | true, false | Primary contact status |

#### Request Body Example:
```json
{
    "relationship": "guardian",
    "is_primary": true
}
```

#### Response Example:
```json
{
    "status": "success",
    "message": "Parent-student relationship updated successfully",
    "data": {
        "student_id": 123,
        "parent_id": 15,
        "parent_name": "Michael Brown",
        "parent_email": "michael.brown@example.com",
        "relationship": "guardian",
        "is_primary": true,
        "updated_at": "2025-08-17 14:30:00"
    }
}
```

---

### **5. Remove Parent from Student**

**DELETE** `/api/students/{studentId}/parents/{parentId}`

Remove the relationship between a parent and student.

#### Request Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `studentId` | integer | ✅ | ID of the student |
| `parentId` | integer | ✅ | ID of the parent |

#### Response Example:
```json
{
    "status": "success",
    "message": "Parent removed from student successfully",
    "data": null
}
```

---

### **6. Get All Parents (for Selection)**

**GET** `/api/parents`

Get a paginated list of all parents for selection in dropdowns or assignment forms.

#### Query Parameters:
| Parameter | Type | Default | Range | Description |
|-----------|------|---------|-------|-------------|
| `page` | integer | 1 | ≥ 1 | Page number |
| `per_page` | integer | 15 | 1-100 | Items per page |
| `search` | string | - | - | Search by name, email, phone, occupation |

#### Response Example:
```json
{
    "status": "success",
    "message": "Parents retrieved successfully",
    "data": {
        "data": [
            {
                "id": 1,
                "name": "John Smith",
                "email": "john.smith@example.com",
                "phone": "+1234567890",
                "occupation": "Engineer",
                "relationship": "father"
            },
            {
                "id": 2,
                "name": "Jane Smith",
                "email": "jane.smith@example.com",
                "phone": "+1234567891",
                "occupation": "Teacher",
                "relationship": "mother"
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 15,
            "total": 73,
            "from": 1,
            "to": 15,
            "has_more_pages": true,
            "prev_page_url": null,
            "next_page_url": "http://localhost:8080/api/parents?page=2"
        }
    }
}
```

---

### **7. Get Parent Details with Children**

**GET** `/api/parents/{parentId}`

Get detailed information about a parent including all their assigned children.

#### Request Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `parentId` | integer | ✅ | ID of the parent |

#### Response Example:
```json
{
    "status": "success",
    "message": "Parent details retrieved successfully",
    "data": {
        "id": 1,
        "name": "John Smith",
        "email": "john.smith@example.com",
        "phone": "+1234567890",
        "alternate_phone": "+1234567891",
        "gender": "male",
        "occupation": "Engineer",
        "address": "123 Main St, City, State",
        "relationship": "father",
        "is_active": true,
        "children": [
            {
                "id": 123,
                "name": "Emily Smith",
                "admission_number": "STU123",
                "relationship": "father",
                "is_primary": true,
                "is_active": true,
                "assigned_at": "2025-08-15 10:30:00"
            },
            {
                "id": 124,
                "name": "James Smith",
                "admission_number": "STU124",
                "relationship": "father",
                "is_primary": true,
                "is_active": true,
                "assigned_at": "2025-08-15 10:35:00"
            }
        ]
    }
}
```

---

### **8. Bulk Assign Parent to Multiple Students**

**POST** `/api/parents/bulk-assign`

Assign a single parent to multiple students at once.

#### Request Parameters:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `parent_id` | integer | ✅ | ID of the parent to assign |
| `student_ids` | array | ✅ | Array of student IDs |
| `student_ids.*` | integer | ✅ | Individual student ID |
| `relationship` | string | ✅ | father, mother, guardian |
| `is_primary` | boolean | ❌ | Primary contact status (default: false) |

#### Request Body Example:
```json
{
    "parent_id": 5,
    "student_ids": [123, 124, 125],
    "relationship": "guardian",
    "is_primary": false
}
```

#### Response Example:
```json
{
    "status": "success",
    "message": "Parent assigned to multiple students successfully",
    "data": {
        "parent": {
            "id": 5,
            "name": "Sarah Johnson",
            "email": "sarah.johnson@example.com"
        },
        "relationship": "guardian",
        "is_primary": false,
        "assigned_students": [
            {
                "student_id": 123,
                "student_name": "Emily Brown",
                "admission_number": "STU123"
            },
            {
                "student_id": 124,
                "student_name": "James Brown",
                "admission_number": "STU124"
            }
        ],
        "skipped_students": [
            {
                "student_id": 125,
                "student_name": "Alex Brown",
                "reason": "Already assigned"
            }
        ],
        "total_assigned": 2,
        "total_skipped": 1
    }
}
```

---

## 🚨 Error Responses

### **Common Error Scenarios:**

#### **1. Module Access Denied (403)**
```json
{
    "status": "error",
    "message": "Access denied. The 'student-management' module is not active for your school.",
    "code": "MODULE_ACCESS_DENIED"
}
```

#### **2. Student Not Found (404)**
```json
{
    "status": "error",
    "message": "Failed to assign parent: Student not found"
}
```

#### **3. Parent Already Assigned (400)**
```json
{
    "status": "error",
    "message": "Failed to assign parent: This parent is already assigned to this student."
}
```

#### **4. Validation Error (422)**
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "email": ["This email address is already registered."],
        "relationship": ["Relationship must be father, mother, or guardian."]
    }
}
```

#### **5. Parent Not Assigned (400)**
```json
{
    "status": "error",
    "message": "Failed to update relationship: This parent is not assigned to this student."
}
```

---

## 💡 Usage Examples

### **JavaScript/Frontend Integration**

#### **1. Get Student's Parents**
```javascript
async function getStudentParents(studentId) {
    const response = await fetch(`/api/students/${studentId}/parents`, {
        headers: {
            'Authorization': 'Bearer ' + token,
            'Accept': 'application/json'
        }
    });
    
    const result = await response.json();
    if (result.status === 'success') {
        return result.data;
    }
    throw new Error(result.message);
}
```

#### **2. Assign Existing Parent**
```javascript
async function assignParent(studentId, parentData) {
    const response = await fetch(`/api/students/${studentId}/parents/assign`, {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(parentData)
    });
    
    return await response.json();
}

// Usage
const assignmentResult = await assignParent(123, {
    parent_id: 5,
    relationship: 'father',
    is_primary: true
});
```

#### **3. Create New Parent**
```javascript
async function createAndAssignParent(studentId, parentData) {
    const response = await fetch(`/api/students/${studentId}/parents/create`, {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(parentData)
    });
    
    return await response.json();
}

// Usage
const newParent = await createAndAssignParent(123, {
    name: 'Michael Brown',
    email: 'michael.brown@example.com',
    password: 'securePassword123',
    phone: '+1234567892',
    relationship: 'father',
    is_primary: true
});
```

#### **4. Bulk Assignment**
```javascript
async function bulkAssignParent(assignmentData) {
    const response = await fetch('/api/parents/bulk-assign', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(assignmentData)
    });
    
    return await response.json();
}

// Usage
const bulkResult = await bulkAssignParent({
    parent_id: 5,
    student_ids: [123, 124, 125],
    relationship: 'guardian',
    is_primary: false
});
```

### **React Hook Example**
```javascript
import { useState, useEffect } from 'react';

function useParentManagement(studentId) {
    const [parents, setParents] = useState([]);
    const [loading, setLoading] = useState(false);
    
    const loadParents = async () => {
        setLoading(true);
        try {
            const parentList = await getStudentParents(studentId);
            setParents(parentList);
        } catch (error) {
            console.error('Failed to load parents:', error);
        } finally {
            setLoading(false);
        }
    };
    
    const assignParent = async (parentData) => {
        const result = await assignParent(studentId, parentData);
        if (result.status === 'success') {
            await loadParents(); // Refresh list
        }
        return result;
    };
    
    useEffect(() => {
        if (studentId) {
            loadParents();
        }
    }, [studentId]);
    
    return { parents, loading, loadParents, assignParent };
}
```

---

## 🛡️ Security Features

### **Multi-Tenant Isolation**
- All operations automatically filter by school_id
- Parents cannot be assigned to students from different schools
- Cross-school data leakage prevention

### **Validation & Constraints**
- Email uniqueness across the platform
- Relationship type validation (father, mother, guardian)
- Primary parent management (only one primary per relationship type)
- Duplicate assignment prevention

### **Access Control**
- Module-based access control (student-management required)
- Role-based permissions (admin, teacher)
- Authentication required for all operations

### **Data Integrity**
- Database transaction support for complex operations
- Automatic primary status management
- Referential integrity maintenance

---

## 📊 Performance Features

### **Database Optimization**
- Efficient queries with proper indexing
- Eager loading of relationships
- Selective field loading for performance

### **Bulk Operations**
- Bulk parent assignment to multiple students
- Transaction-based operations for data consistency
- Batch processing for large operations

### **Pagination Support**
- Configurable page sizes (1-100 items)
- Efficient pagination with metadata
- Search functionality across parent listings

---

## 🧪 Testing Examples

### **cURL Examples**

#### **Get Student Parents**
```bash
curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     "http://localhost:8080/api/students/123/parents"
```

#### **Assign Existing Parent**
```bash
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"parent_id": 5, "relationship": "father", "is_primary": true}' \
     "http://localhost:8080/api/students/123/parents/assign"
```

#### **Create New Parent**
```bash
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
         "name": "Michael Brown",
         "email": "michael.brown@example.com", 
         "password": "securePassword123",
         "phone": "+1234567892",
         "relationship": "father",
         "is_primary": true
     }' \
     "http://localhost:8080/api/students/123/parents/create"
```

#### **Bulk Assignment**
```bash
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
         "parent_id": 5,
         "student_ids": [123, 124, 125],
         "relationship": "guardian",
         "is_primary": false
     }' \
     "http://localhost:8080/api/parents/bulk-assign"
```

---

## 🎯 Use Cases

### **Perfect For:**
- 👥 **Student Enrollment**: Assign parents during admission process
- 🔄 **Family Management**: Handle family structure changes
- 📧 **Contact Management**: Maintain updated parent contact information
- 🏫 **Bulk Operations**: Assign guardians to multiple students (daycare scenarios)
- 📱 **Mobile App**: Parent-student relationship verification for mobile access
- 📊 **Reporting**: Generate parent-student relationship reports

### **Common Workflows:**
1. **New Student Admission**: Create parent accounts and assign to student
2. **Existing Family Addition**: Assign existing parent to new sibling
3. **Guardian Assignment**: Assign temporary guardians to students
4. **Contact Updates**: Update parent-student relationship status
5. **Bulk Guardian Assignment**: Assign bus supervisors or daycare staff

---

## 📈 Database Schema

### **Tables Involved:**
- `users` - Parent user accounts
- `parents` - Parent profile information  
- `students` - Student records
- `parent_student` - Pivot table for relationships

### **parent_student Pivot Table:**
| Column | Type | Description |
|--------|------|-------------|
| `parent_id` | int | Foreign key to parents table |
| `student_id` | int | Foreign key to students table |
| `relationship` | varchar | father, mother, guardian |
| `is_primary` | boolean | Primary contact status |
| `created_at` | timestamp | Assignment date |
| `updated_at` | timestamp | Last update date |

---

This comprehensive Parent-Student Management API system provides all the necessary tools to handle complex parent-student relationships while maintaining security, performance, and data integrity across the multi-tenant SchoolSavvy platform.
