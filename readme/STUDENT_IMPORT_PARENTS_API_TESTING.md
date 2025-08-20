# Student Import with Parents - API Testing Guide

## Quick Test Scenario

### Step 1: Download Template
```bash
curl -X GET "http://localhost:8080/api/students/import/template" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -o student_import_template.csv
```

### Step 2: Prepare Test Data
Create a CSV file with the following data:

```csv
admission_number,first_name,last_name,date_of_birth,gender,admission_date,blood_group,address,phone,class_name,class_section,roll_number,father_name,father_email,father_phone,father_occupation,father_address,mother_name,mother_email,mother_phone,mother_occupation,mother_address,guardian_name,guardian_email,guardian_phone,guardian_relationship,guardian_occupation,guardian_address
STU2025001,Alice,Smith,2016-03-15,female,2025-01-15,A+,123 Test Street,9876543210,Grade 1,A,1,John Smith,john.smith@test.com,9876543220,Software Engineer,123 Test Street,Mary Smith,mary.smith@test.com,9876543221,Doctor,123 Test Street,,,,,,,
STU2025002,Bob,Johnson,2015-07-22,male,2025-01-15,B+,456 Demo Avenue,9876543211,Grade 2,B,2,Robert Johnson,robert.johnson@test.com,9876543222,Teacher,456 Demo Avenue,,,,,,Sarah Wilson,sarah.wilson@test.com,9876543223,aunt,Businesswoman,789 Guardian Road
STU2025003,Charlie,Brown,2017-01-10,other,2025-01-15,O+,789 Sample Lane,9876543212,Grade 1,B,3,,,,,,,Lisa Brown,lisa.brown@test.com,9876543224,Nurse,789 Sample Lane,,,,,,,
```

### Step 3: Upload File
```bash
curl -X POST "http://localhost:8080/api/uploads/single" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@test_students.csv" \
  -F "type=bulk_import"
```

**Expected Response**:
```json
{
    "status": "success",
    "message": "File uploaded successfully",
    "data": {
        "file_path": "bulk_import/20250820/students_20250820_123456.csv",
        "file_name": "test_students.csv",
        "file_size": 1024,
        "file_url": "http://localhost:8080/storage/bulk_import/20250820/students_20250820_123456.csv"
    }
}
```

### Step 4: Initiate Import
```bash
curl -X POST "http://localhost:8080/api/students/import" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "file_path": "bulk_import/20250820/students_20250820_123456.csv",
    "file_name": "test_students.csv"
  }'
```

**Expected Response**:
```json
{
    "status": "success",
    "message": "Student import initiated successfully",
    "data": {
        "import_id": 1,
        "status": "pending",
        "file_name": "test_students.csv",
        "total_rows": 0,
        "processed_rows": 0,
        "success_count": 0,
        "failed_count": 0
    }
}
```

### Step 5: Monitor Progress
```bash
curl -X GET "http://localhost:8080/api/students/import/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Progress Response**:
```json
{
    "status": "success",
    "data": {
        "id": 1,
        "status": "completed",
        "file_name": "test_students.csv",
        "total_rows": 3,
        "processed_rows": 3,
        "success_count": 3,
        "failed_count": 0,
        "started_at": "2025-08-20T10:30:00Z",
        "completed_at": "2025-08-20T10:31:15Z"
    }
}
```

### Step 6: Verify Results

#### Check Created Students
```bash
curl -X GET "http://localhost:8080/api/students?per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Check Parent Accounts Created
```bash
curl -X GET "http://localhost:8080/api/parents" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Step 7: Test Error Scenarios

Create a CSV with errors:
```csv
admission_number,first_name,last_name,date_of_birth,gender,admission_date,blood_group,address,phone,class_name,class_section,roll_number,father_name,father_email,father_phone,father_occupation,father_address,mother_name,mother_email,mother_phone,mother_occupation,mother_address,guardian_name,guardian_email,guardian_phone,guardian_relationship,guardian_occupation,guardian_address
,Missing,Admission,,invalid_gender,2025-01-15,A+,Test Address,9876543210,Grade 1,A,1,John Doe,invalid-email,9876543220,Engineer,Test Address,,,,,,,,,,,
STU2025001,Duplicate,Number,2016-03-15,female,2025-01-15,A+,Another Address,9876543211,Grade 2,B,2,,,,,,,Jane Doe,jane.doe@test.com,9876543222,Teacher,Another Address,,,,,,,
```

Upload and import this file, then check errors:
```bash
curl -X GET "http://localhost:8080/api/students/import/2/errors" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Test Results Verification

### Database Verification
After successful import, verify the following records were created:

#### Students Table
```sql
SELECT * FROM students WHERE school_id = YOUR_SCHOOL_ID ORDER BY created_at DESC LIMIT 10;
```

#### Users Table (Parent Accounts)
```sql
SELECT * FROM users WHERE user_type = 'parent' ORDER BY created_at DESC LIMIT 10;
```

#### Parents Table
```sql
SELECT p.*, u.name, u.email 
FROM parents p 
JOIN users u ON p.user_id = u.id 
ORDER BY p.created_at DESC LIMIT 10;
```

#### Parent-Student Relationships
```sql
SELECT ps.*, 
       s.first_name || ' ' || s.last_name as student_name,
       u.name as parent_name,
       u.email as parent_email
FROM parent_student ps
JOIN students s ON ps.student_id = s.id
JOIN parents p ON ps.parent_id = p.id
JOIN users u ON p.user_id = u.id
ORDER BY ps.created_at DESC LIMIT 10;
```

### Expected Results for Test Data

#### Student STU2025001 (Alice Smith)
- **Student Record**: Alice Smith created
- **Father Account**: John Smith (john.smith@test.com) 
- **Mother Account**: Mary Smith (mary.smith@test.com)
- **Relationships**: 
  - Father → Primary relationship
  - Mother → Secondary relationship

#### Student STU2025002 (Bob Johnson)  
- **Student Record**: Bob Johnson created
- **Father Account**: Robert Johnson (robert.johnson@test.com)
- **Guardian Account**: Sarah Wilson (sarah.wilson@test.com) 
- **Relationships**:
  - Father → Primary relationship  
  - Guardian (aunt) → Secondary relationship

#### Student STU2025003 (Charlie Brown)
- **Student Record**: Charlie Brown created
- **Mother Account**: Lisa Brown (lisa.brown@test.com)
- **Relationships**:
  - Mother → Primary relationship (only parent)

## Error Testing Scenarios

### Common Error Cases to Test

1. **Missing Required Fields**: Empty admission_number, first_name, etc.
2. **Invalid Data Formats**: Invalid dates, email formats
3. **Duplicate Records**: Same admission_number within school
4. **Invalid Relationships**: Invalid gender values, guardian relationships
5. **Partial Parent Data**: Name provided but missing email
6. **Non-existent Classes**: Class names that don't exist in school

### Error Response Examples

```json
{
    "status": "success",
    "data": {
        "data": [
            {
                "id": 1,
                "row_number": 2,
                "row_data": {
                    "admission_number": "",
                    "first_name": "Missing",
                    "last_name": "Admission"
                },
                "errors": {
                    "validation": "Admission number is required"
                },
                "created_at": "2025-08-20T10:35:00Z"
            },
            {
                "id": 2, 
                "row_number": 3,
                "row_data": {
                    "admission_number": "STU2025001",
                    "first_name": "Duplicate"
                },
                "errors": {
                    "validation": "Student with admission number STU2025001 already exists"
                },
                "created_at": "2025-08-20T10:35:01Z"
            }
        ]
    }
}
```

## Performance Testing

### Large File Testing
Create a CSV with 1000+ rows to test:
- Background job processing
- Memory usage
- Processing time
- Error handling at scale

### Concurrent Import Testing
- Test multiple simultaneous imports
- Verify queue system handles concurrent jobs
- Check database locking and integrity

## Cleanup After Testing

### Remove Test Data
```sql
-- Delete test students (will cascade to related records)
DELETE FROM students WHERE admission_number LIKE 'STU2025%';

-- Delete test parent users
DELETE FROM users WHERE email LIKE '%@test.com' AND user_type = 'parent';

-- Clean up import records
DELETE FROM student_imports WHERE file_name LIKE 'test_%';
```

### Reset Auto-increment (if needed)
```sql
-- Reset auto-increment values if needed
ALTER TABLE students AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE student_imports AUTO_INCREMENT = 1;
```

This testing guide ensures comprehensive validation of the enhanced import system with parent/guardian support.
