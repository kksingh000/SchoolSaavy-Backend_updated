# Student Creation API with Class Assignment

## Overview

The student creation and update APIs now support direct class assignment during student creation/update operations. This enhancement allows schools to create students and immediately assign them to classes in a single API call.

## Enhanced API Endpoints

### 1. Create Student with Class Assignment

```http
POST /api/students
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
    "school_id": 1,
    "admission_number": "STU2025001",
    "roll_number": "1",
    "first_name": "John",
    "last_name": "Doe",
    "date_of_birth": "2015-05-15",
    "gender": "male",
    "admission_date": "2025-01-15",
    "blood_group": "A+",
    "address": "123 Main Street, City",
    "phone": "9876543210",
    "class_id": 5,
    "class_roll_number": "15",
    "parent_id": 10,
    "relationship": "father",
    "is_primary": true,
    "profile_photo": "uploads/profiles/student_photo.jpg"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Student created successfully",
    "data": {
        "id": 123,
        "admission_number": "STU2025001",
        "first_name": "John",
        "last_name": "Doe",
        "name": "John Doe",
        "date_of_birth": "2015-05-15",
        "gender": "male",
        "admission_date": "2025-01-15",
        "blood_group": "A+",
        "address": "123 Main Street, City",
        "phone": "9876543210",
        "is_active": true,
        "profile_photo_url": "https://s3.amazonaws.com/bucket/uploads/profiles/student_photo.jpg",
        "current_class": {
            "id": 5,
            "name": "Grade 5",
            "section": "A",
            "class_teacher": "Mrs. Johnson"
        },
        "class_id": 5,
        "class_name": "Grade 5 - A",
        "parents": [
            {
                "id": 10,
                "name": "Robert Doe",
                "email": "robert.doe@email.com",
                "phone": "9876543220",
                "relationship": "father",
                "is_primary": true
            }
        ],
        "created_at": "2025-08-20T10:30:00Z",
        "updated_at": "2025-08-20T10:30:00Z"
    }
}
```

### 2. Update Student with Class Assignment

```http
PUT /api/students/{student_id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body (Partial Update Example):**
```json
{
    "class_id": 7,
    "class_roll_number": "22",
    "first_name": "Jonathan",
    "phone": "9876543299"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Student updated successfully",
    "data": {
        "id": 123,
        "admission_number": "STU2025001",
        "first_name": "Jonathan",
        "last_name": "Doe",
        "current_class": {
            "id": 7,
            "name": "Grade 6",
            "section": "B",
            "class_teacher": "Mr. Smith"
        },
        "class_id": 7,
        "class_name": "Grade 6 - B"
    }
}
```

## Field Specifications

### Required Fields (Creation)
- `school_id` - School identifier (auto-injected by middleware)
- `admission_number` - Unique student identifier within school
- `roll_number` - General roll number for student
- `first_name` - Student's first name
- `last_name` - Student's last name
- `date_of_birth` - Birth date (must be in the past)
- `gender` - male, female, or other
- `admission_date` - Date of admission to school
- `address` - Residential address
- `parent_id` - ID of parent/guardian to associate
- `relationship` - father, mother, or guardian

### Optional Fields
- `blood_group` - A+, A-, B+, B-, O+, O-, AB+, AB-
- `phone` - Contact number
- `is_primary` - Whether this parent is primary contact (default: true)
- `profile_photo` - S3 path from upload API
- `class_id` - Class to assign student to
- `class_roll_number` - Specific roll number in the class (auto-generated if not provided)

### Class Assignment Rules

#### During Creation
- If `class_id` provided: Student is immediately assigned to the class
- If `class_roll_number` provided: Uses specific roll number
- If `class_roll_number` not provided: Auto-generates next available roll number
- Class must belong to the same school and be active

#### During Update
- If `class_id` provided: Moves student to new class (deactivates old assignment)
- If `class_id` is null: Removes student from current class
- If `class_id` same as current: Updates roll number if provided
- Maintains class assignment history with dates

## Validation Rules

### Class Validation
```php
// Class must exist
'class_id' => 'nullable|exists:classes,id'

// Custom validations:
// 1. Class must belong to same school
// 2. Class must be active
// 3. Roll number must be unique within class (if provided)
```

### Roll Number Logic
```php
// Auto-generation logic:
$lastRollNumber = DB::table('class_student')
    ->where('class_id', $classId)
    ->where('is_active', true)
    ->max('roll_number');
    
$newRollNumber = $lastRollNumber ? $lastRollNumber + 1 : 1;
```

## Example Use Cases

### 1. Create Student with Immediate Class Assignment
```json
{
    "admission_number": "STU2025001",
    "first_name": "Alice",
    "last_name": "Smith",
    "date_of_birth": "2016-03-15",
    "gender": "female",
    "admission_date": "2025-08-20",
    "address": "123 Test Street",
    "class_id": 3,
    "parent_id": 5,
    "relationship": "mother"
}
```

### 2. Create Student without Class (Assign Later)
```json
{
    "admission_number": "STU2025002",
    "first_name": "Bob",
    "last_name": "Johnson",
    "date_of_birth": "2015-07-22",
    "gender": "male",
    "admission_date": "2025-08-20",
    "address": "456 Demo Avenue",
    "parent_id": 6,
    "relationship": "father"
}
```

### 3. Transfer Student to Different Class
```json
{
    "class_id": 8,
    "class_roll_number": "25"
}
```

### 4. Remove Student from Class
```json
{
    "class_id": null
}
```

## Error Handling

### Common Validation Errors

#### Invalid Class ID
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "class_id": ["The selected class does not belong to your school."]
    }
}
```

#### Duplicate Roll Number
```json
{
    "status": "error",
    "message": "Roll number 15 is already taken in this class"
}
```

#### Inactive Class
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "class_id": ["The selected class is not active."]
    }
}
```

#### Duplicate Student Assignment
```json
{
    "status": "error",
    "message": "Student is already assigned to this class"
}
```

## Database Operations

### Class Assignment Process
1. **Validation**: Verify class exists, belongs to school, and is active
2. **Conflict Check**: Ensure roll number is not taken in target class
3. **Deactivation**: Deactivate current class assignment (for updates)
4. **Assignment**: Create new class assignment with roll number and dates
5. **History**: Previous assignments remain in database with `is_active=false`

### Database Tables Affected
- `students` - Student basic information
- `class_student` - Class assignments with roll numbers and dates
- `parent_student` - Parent-student relationships

### Class Assignment Record Structure
```php
// class_student table
[
    'student_id' => 123,
    'class_id' => 5,
    'roll_number' => 15,
    'enrolled_date' => '2025-08-20',
    'left_date' => null,
    'is_active' => true,
    'academic_year_id' => 1 // If academic year system is used
]
```

## Integration with Existing Systems

### Academic Year Integration
If your school uses academic years, the class assignment will automatically use the current academic year context.

### Attendance System
Once assigned to a class, the student immediately becomes available for attendance marking in that class.

### Assignment System
Student can receive assignments for the assigned class and subjects.

### Promotion System
Class assignments are tracked for promotion decisions at year-end.

## Performance Considerations

### Optimized Queries
- Uses `findOrFail()` for student lookups with school_id filtering
- Efficient roll number generation using `max()` query
- Bulk updates for class transfers to minimize database calls

### Caching Implications
- Class assignment changes may affect cached student lists
- Clear relevant caches when class assignments change
- Consider caching class student counts for performance

## Security Features

### Multi-Tenant Isolation
- All class lookups filtered by `school_id`
- Students cannot be assigned to classes from other schools
- Roll number uniqueness enforced per class per school

### Data Integrity
- Transaction-based operations ensure consistency
- Soft deletes preserve assignment history
- Audit trail via timestamps on pivot table

## Testing Examples

### Test Class Assignment During Creation
```bash
curl -X POST "http://localhost:8080/api/students" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "admission_number": "TEST001",
    "first_name": "Test",
    "last_name": "Student",
    "date_of_birth": "2016-01-01",
    "gender": "male",
    "admission_date": "2025-08-20",
    "address": "Test Address",
    "class_id": 1,
    "class_roll_number": "99",
    "parent_id": 1,
    "relationship": "father"
  }'
```

### Test Class Transfer During Update
```bash
curl -X PUT "http://localhost:8080/api/students/123" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "class_id": 2,
    "class_roll_number": "10"
  }'
```

### Test Remove from Class
```bash
curl -X PUT "http://localhost:8080/api/students/123" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "class_id": null
  }'
```

This enhancement provides a seamless way to manage student-class assignments directly through the student creation and update APIs, reducing the need for separate class assignment operations.
