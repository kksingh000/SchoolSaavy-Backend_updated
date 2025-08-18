# Standalone Parent Creation API Documentation

## 📋 Overview

The Standalone Parent Creation API allows administrators to create parent accounts without immediately assigning them to students. This is useful for pre-registration scenarios, bulk parent creation, or when parent information is collected separately from student enrollment.

---

## 🚀 New API Endpoint

### **POST /api/parents**

Create a new parent account without student assignment.

#### **Purpose**: 
- Create parent accounts for future assignment
- Pre-register parents before student enrollment
- Bulk parent account creation
- Separate parent and student management workflows

---

## 📡 Request Details

### **Authentication & Authorization:**
- **Bearer Token**: Required
- **Module Access**: Requires `student-management` module
- **User Types**: Available to `admin` and authorized `teacher` roles
- **Multi-Tenant**: Automatically isolated by school

### **Request Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| `name` | string | ✅ | max:255 | Full name of the parent |
| `email` | string | ✅ | email, unique | Unique email address |
| `password` | string | ✅ | min:6 chars | Account password |
| `phone` | string | ✅ | max:20 | Primary phone number |
| `alternate_phone` | string | ❌ | max:20 | Secondary phone number |
| `gender` | string | ❌ | male, female, other | Parent's gender |
| `occupation` | string | ❌ | max:255 | Parent's occupation |
| `address` | string | ❌ | max:500 | Home address |
| `relationship` | string | ✅ | father, mother, guardian | Default relationship type |

---

## 📤 Request & Response Examples

### **Request Body:**
```json
{
    "name": "John Smith",
    "email": "john.smith@example.com",
    "password": "securePassword123",
    "phone": "+1234567890",
    "alternate_phone": "+1234567891",
    "gender": "male",
    "occupation": "Software Engineer",
    "address": "123 Main Street, City, State 12345",
    "relationship": "father"
}
```

### **Success Response (201 Created):**
```json
{
    "status": "success",
    "message": "Parent created successfully",
    "data": {
        "id": 25,
        "name": "John Smith",
        "email": "john.smith@example.com",
        "phone": "+1234567890",
        "alternate_phone": "+1234567891",
        "gender": "male",
        "occupation": "Software Engineer",
        "address": "123 Main Street, City, State 12345",
        "relationship": "father",
        "is_active": true,
        "created_at": "2025-08-17 15:30:00"
    }
}
```

### **Validation Error Response (422):**
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "email": ["This email address is already registered."],
        "phone": ["Phone number is required."],
        "relationship": ["Relationship must be father, mother, or guardian."]
    }
}
```

### **Module Access Denied (403):**
```json
{
    "status": "error",
    "message": "Access denied. The 'student-management' module is not active for your school.",
    "code": "MODULE_ACCESS_DENIED"
}
```

---

## 💻 Usage Examples

### **JavaScript/Frontend Integration:**

#### **Basic Parent Creation:**
```javascript
async function createParent(parentData) {
    const response = await fetch('/api/parents', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(parentData)
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
        console.log('Parent created:', result.data);
        return result.data;
    } else {
        throw new Error(result.message);
    }
}

// Usage
try {
    const newParent = await createParent({
        name: 'Jane Doe',
        email: 'jane.doe@example.com',
        password: 'securePass123',
        phone: '+1987654321',
        gender: 'female',
        occupation: 'Teacher',
        relationship: 'mother'
    });
    
    // Parent created successfully - can now assign to students later
    console.log('Created parent ID:', newParent.id);
} catch (error) {
    console.error('Failed to create parent:', error.message);
}
```

#### **Form Handling with Validation:**
```javascript
function handleParentForm(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const parentData = Object.fromEntries(formData.entries());
    
    createParent(parentData)
        .then(parent => {
            showSuccessMessage(`Parent ${parent.name} created successfully!`);
            resetForm();
            // Optionally redirect to assignment page
            // window.location.href = `/students/assign-parent?parent_id=${parent.id}`;
        })
        .catch(error => {
            showErrorMessage(error.message);
        });
}
```

#### **React Hook for Parent Creation:**
```javascript
import { useState } from 'react';

function useParentCreation() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    
    const createParent = async (parentData) => {
        setLoading(true);
        setError(null);
        
        try {
            const response = await fetch('/api/parents', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + getToken(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(parentData)
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                return result.data;
            } else {
                throw new Error(result.message);
            }
        } catch (err) {
            setError(err.message);
            throw err;
        } finally {
            setLoading(false);
        }
    };
    
    return { createParent, loading, error };
}

// Usage in component
function CreateParentForm() {
    const { createParent, loading, error } = useParentCreation();
    
    const handleSubmit = async (formData) => {
        try {
            const parent = await createParent(formData);
            console.log('Parent created:', parent);
            // Handle success (redirect, show message, etc.)
        } catch (error) {
            console.error('Creation failed:', error);
        }
    };
    
    return (
        // Your form JSX here
        <form onSubmit={handleSubmit}>
            {/* Form fields */}
            <button type="submit" disabled={loading}>
                {loading ? 'Creating...' : 'Create Parent'}
            </button>
            {error && <div className="error">{error}</div>}
        </form>
    );
}
```

### **cURL Example:**
```bash
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
         "name": "Michael Johnson",
         "email": "michael.johnson@example.com",
         "password": "mySecurePassword123",
         "phone": "+1555123456",
         "alternate_phone": "+1555123457",
         "gender": "male",
         "occupation": "Doctor",
         "address": "456 Oak Avenue, Springfield, IL 62701",
         "relationship": "father"
     }' \
     "http://localhost:8080/api/parents"
```

---

## 🔄 Integration with Existing APIs

### **Workflow: Create Parent → Assign to Student**

#### **Step 1: Create Parent**
```javascript
const parent = await createParent({
    name: 'Sarah Wilson',
    email: 'sarah.wilson@example.com',
    password: 'password123',
    phone: '+1555987654',
    relationship: 'mother'
});
```

#### **Step 2: Assign to Student**
```javascript
const assignment = await fetch(`/api/students/${studentId}/parents/assign`, {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        parent_id: parent.id,
        relationship: 'mother',
        is_primary: true
    })
});
```

### **Combined Function:**
```javascript
async function createAndAssignParent(studentId, parentData, assignmentData) {
    try {
        // Create parent first
        const parent = await createParent(parentData);
        
        // Then assign to student
        const assignment = await assignParentToStudent(studentId, {
            parent_id: parent.id,
            relationship: assignmentData.relationship,
            is_primary: assignmentData.is_primary
        });
        
        return {
            parent: parent,
            assignment: assignment
        };
    } catch (error) {
        console.error('Failed to create and assign parent:', error);
        throw error;
    }
}
```

---

## 🛡️ Security & Validation

### **Validation Rules:**
- **Email Uniqueness**: Validated across entire platform
- **Password Strength**: Minimum 6 characters (can be enhanced)
- **Required Fields**: name, email, password, phone, relationship
- **Enum Validation**: gender (male/female/other), relationship (father/mother/guardian)

### **Security Features:**
- **Multi-Tenant Isolation**: Parents belong to authenticated user's school
- **Module Access Control**: Requires student-management module
- **Password Hashing**: Automatic secure password hashing
- **Email Verification**: Timestamp set for immediate account activation

---

## 🎯 Use Cases

### **Perfect For:**
- 📝 **Pre-Registration**: Create parent accounts before student enrollment
- 📊 **Bulk Import**: Mass creation of parent accounts from CSV/Excel
- 🏫 **Open Enrollment**: Allow parents to register before school starts
- 🔄 **Staged Process**: Separate parent creation from student assignment
- 📱 **Self-Service**: Parents can create their own accounts (with modifications)
- 🎯 **Admin Workflow**: Create parents, then batch assign to students

### **Business Scenarios:**
1. **School Registration Drive**: Create parent accounts during community events
2. **Sibling Enrollment**: Parent exists, just needs assignment to new student
3. **Transfer Students**: Parent data collected separately from student records
4. **Batch Processing**: Import parent list, then assign based on matching logic
5. **Incomplete Enrollments**: Parent registered but student not yet enrolled

---

## 🔄 Changes Summary

### **✅ Added:**
- **POST /api/parents** - Standalone parent creation API
- **StoreParentRequest** - Dedicated form request validation
- **createParent()** method in ParentStudentService
- **createParent()** method in ParentStudentController

### **🗑️ Removed:**
- **POST /api/parents/bulk-assign** - Bulk assignment endpoint
- **bulkAssignParent()** methods from controller and service
- Related validation and documentation for bulk operations

### **📍 Updated Routes:**
```php
// Before
Route::prefix('parents')->group(function () {
    Route::get('/', [ParentStudentController::class, 'getAllParents']);
    Route::get('{parentId}', [ParentStudentController::class, 'getParentDetails']);
    Route::post('bulk-assign', [ParentStudentController::class, 'bulkAssignParent']); // REMOVED
});

// After  
Route::prefix('parents')->group(function () {
    Route::get('/', [ParentStudentController::class, 'getAllParents']);
    Route::post('/', [ParentStudentController::class, 'createParent']); // NEW
    Route::get('{parentId}', [ParentStudentController::class, 'getParentDetails']);
});
```

---

## 📊 Complete API Overview

### **Available Parent Management Endpoints:**

| Method | Endpoint | Purpose |
|--------|----------|---------|
| **POST** | `/api/parents` | **NEW** - Create standalone parent |
| **GET** | `/api/parents` | Get all parents (paginated, searchable) |
| **GET** | `/api/parents/{id}` | Get parent details with children |
| **GET** | `/api/students/{id}/parents` | Get student's assigned parents |
| **POST** | `/api/students/{id}/parents/assign` | Assign existing parent to student |
| **POST** | `/api/students/{id}/parents/create` | Create parent + assign to student |
| **PUT** | `/api/students/{studentId}/parents/{parentId}` | Update parent-student relationship |
| **DELETE** | `/api/students/{studentId}/parents/{parentId}` | Remove parent from student |

This new standalone parent creation API provides the flexibility to manage parent accounts independently from student assignments, enabling more sophisticated workflows and better separation of concerns in the parent management system.
