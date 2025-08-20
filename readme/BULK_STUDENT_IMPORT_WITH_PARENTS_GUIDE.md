# Bulk Student Import with Parents/Guardians Guide

## Overview

The SchoolSavvy bulk student import system now supports importing students along with their parents and guardians information. This enhanced system allows schools to efficiently import complete student profiles including family relationships in a single CSV upload.

## Features

✅ **Student Import**: Basic student information (name, admission number, class, etc.)
✅ **Parent Import**: Father and Mother information with automatic account creation
✅ **Guardian Import**: Guardian/Other relationship support
✅ **Automatic User Creation**: Creates parent accounts with login credentials
✅ **Relationship Management**: Assigns parents to students with proper relationships
✅ **Error Handling**: Detailed error reporting for each row
✅ **Background Processing**: Handles large imports asynchronously

## Database Schema

### Parent-Student Relationship Structure

```
Users Table (Authentication)
├── id, name, email, password, user_type='parent'

Parents Table (Profile Information)  
├── id, user_id, phone, gender, occupation, address, relationship

Students Table
├── id, school_id, admission_number, first_name, last_name, etc.

Parent_Student Table (Many-to-Many Relationship)
├── parent_id, student_id, relationship, is_primary
```

## CSV Template Structure

### Required Fields (Student Information)
- `admission_number` - Unique identifier for student
- `first_name` - Student's first name  
- `last_name` - Student's last name
- `date_of_birth` - Format: YYYY-MM-DD
- `gender` - male, female, or other

### Optional Fields (Student Information)
- `admission_date` - Format: YYYY-MM-DD (defaults to current date)
- `blood_group` - Student's blood group
- `address` - Student's residential address
- `phone` - Student's contact number
- `class_name` - Class name for enrollment
- `class_section` - Class section
- `roll_number` - Roll number in class

### Optional Fields (Father Information)
- `father_name` - Father's full name
- `father_email` - Father's email (required if father_name provided)
- `father_phone` - Father's contact number
- `father_occupation` - Father's profession
- `father_address` - Father's address

### Optional Fields (Mother Information)
- `mother_name` - Mother's full name
- `mother_email` - Mother's email (required if mother_name provided)
- `mother_phone` - Mother's contact number
- `mother_occupation` - Mother's profession
- `mother_address` - Mother's address

### Optional Fields (Guardian Information)
- `guardian_name` - Guardian's full name
- `guardian_email` - Guardian's email (required if guardian_name provided)
- `guardian_phone` - Guardian's contact number
- `guardian_relationship` - uncle, aunt, grandfather, grandmother, guardian, other
- `guardian_occupation` - Guardian's profession
- `guardian_address` - Guardian's address

## CSV Template Example

```csv
admission_number,first_name,last_name,date_of_birth,gender,admission_date,blood_group,address,phone,class_name,class_section,roll_number,father_name,father_email,father_phone,father_occupation,father_address,mother_name,mother_email,mother_phone,mother_occupation,mother_address,guardian_name,guardian_email,guardian_phone,guardian_relationship,guardian_occupation,guardian_address
STU001,John,Doe,2015-05-15,male,2024-04-01,A+,123 Main Street,9876543210,Grade 1,A,1,Robert Doe,robert.doe@email.com,9876543220,Engineer,123 Main Street,Mary Doe,mary.doe@email.com,9876543221,Teacher,123 Main Street,,,,,,,
STU002,Jane,Smith,2014-08-22,female,2024-04-01,B+,456 Oak Avenue,9876543211,Grade 2,B,2,Michael Smith,michael.smith@email.com,9876543222,Doctor,456 Oak Avenue,,,,,,Sarah Johnson,sarah.johnson@email.com,9876543223,aunt,Businesswoman,789 Guardian Street
STU003,Alex,Johnson,2016-01-10,other,2024-04-01,O+,789 Pine Road,9876543212,Grade 3,A,3,,,,,,,Lisa Johnson,lisa.johnson@email.com,9876543224,Nurse,789 Pine Road,,,,,,,
```

## API Endpoints

### 1. Download Import Template

```http
GET /api/students/import/template
Authorization: Bearer {token}
```

**Response**: Downloads CSV template file with proper headers and sample data.

### 2. Import Students with Parents

```http
POST /api/students/import
Authorization: Bearer {token}
Content-Type: application/json

{
    "file_path": "imports/students_20250820_123456.csv",
    "file_name": "students_import.csv"
}
```

**Response**:
```json
{
    "status": "success",
    "message": "Student import initiated successfully",
    "data": {
        "import_id": 123,
        "status": "pending", 
        "file_name": "students_import.csv",
        "total_rows": 0,
        "processed_rows": 0,
        "success_count": 0,
        "failed_count": 0
    }
}
```

### 3. Monitor Import Progress

```http
GET /api/students/import/{import_id}
Authorization: Bearer {token}
```

**Response**:
```json
{
    "status": "success",
    "data": {
        "id": 123,
        "status": "processing",
        "file_name": "students_import.csv",
        "total_rows": 150,
        "processed_rows": 75,
        "success_count": 70,
        "failed_count": 5,
        "started_at": "2025-08-20T10:30:00Z",
        "completed_at": null,
        "user": {
            "id": 1,
            "name": "School Admin"
        }
    }
}
```

### 4. View Import Errors

```http
GET /api/students/import/{import_id}/errors
Authorization: Bearer {token}
```

**Response**:
```json
{
    "status": "success", 
    "data": {
        "data": [
            {
                "id": 456,
                "row_number": 15,
                "row_data": {
                    "admission_number": "STU015",
                    "first_name": "Test",
                    "father_email": "invalid-email"
                },
                "errors": {
                    "validation": "Invalid email format for father"
                },
                "created_at": "2025-08-20T10:35:00Z"
            }
        ],
        "pagination": "..."
    }
}
```

## Import Process Flow

### 1. File Upload
- Use existing File Upload API (`POST /api/uploads/single`) with type `bulk_import`
- Supported formats: CSV, XLSX, XLS
- Maximum file size: 10MB

### 2. Import Initiation
- Call import API with uploaded file path
- System validates CSV structure and creates import record
- Background job is queued for processing

### 3. Background Processing
- Reads CSV file in chunks (100 rows at a time)
- For each row:
  - Validates and creates student record
  - Creates parent/guardian user accounts (if provided)
  - Links parents to students with proper relationships
  - Assigns students to classes
  - Logs any errors for failed rows

### 4. Parent Account Creation
- Creates User record with `user_type='parent'`
- Generates default password: `password123`
- Creates Parent profile record
- Links to student via `parent_student` relationship table

## Validation Rules

### Student Validation
- `admission_number`: Required, unique within school
- `first_name` and `last_name`: Required
- `date_of_birth`: Required, valid date format
- `gender`: Must be 'male', 'female', or 'other'

### Parent/Guardian Validation  
- If `{type}_name` provided, `{type}_email` is required
- Email must be valid format
- Phone numbers are optional but validated if provided
- Guardian relationship must be from allowed values
- Duplicate parent emails are handled (existing accounts linked)

### Class Assignment
- Class must exist in school and be active
- If class not found, student created without class assignment
- Roll number defaults to student ID if not provided

## Error Handling

### Row-Level Errors
- Invalid student data (missing required fields, invalid formats)
- Duplicate admission numbers within school  
- Parent/guardian creation failures
- Class assignment failures

### Parent Creation Errors
- Invalid email formats
- Missing required parent information
- User account creation failures

### System Errors
- File not found or corrupted
- Database connection issues
- Memory/timeout errors for large files

## Best Practices

### CSV Preparation
1. **Use Template**: Always download and use the official template
2. **Data Validation**: Validate data before upload (emails, dates, etc.)
3. **Small Batches**: For large datasets, consider splitting into smaller files
4. **Backup**: Keep backup of original data

### Parent Information
1. **Complete Data**: Provide complete parent info (name + email) or leave empty
2. **Unique Emails**: Ensure parent emails are unique and valid
3. **Relationships**: Use correct relationship values for guardians
4. **Contact Info**: Provide phone numbers for communication

### Error Resolution
1. **Monitor Progress**: Check import status regularly
2. **Review Errors**: Download and fix error rows
3. **Re-import**: Create new CSV with only failed rows and re-import
4. **Manual Entry**: For complex cases, consider manual entry

## Security Features

### Data Protection
- Multi-tenant isolation (school_id filtering)
- User authentication required for all operations
- File access restricted to school users
- Automatic file cleanup after processing

### Parent Account Security
- Default passwords (should be changed on first login)
- Email verification recommended
- Account activation workflow
- Role-based access control

## Performance Considerations

### Large File Handling
- Background job processing prevents timeouts
- Chunked processing (100 rows per batch) 
- Progress tracking and reporting
- Memory-efficient CSV reading

### Database Optimization
- Bulk inserts where possible
- Transaction management for data consistency
- Index utilization for lookups
- Connection pooling for concurrent processing

## Monitoring and Troubleshooting

### Import Status Monitoring
```http
# Get all imports for school
GET /api/students/import

# Check specific import status  
GET /api/students/import/{id}

# Monitor errors
GET /api/students/import/{id}/errors?page=1&per_page=50
```

### Common Issues

#### Import Stuck in "Processing"
- Check queue worker status: `php artisan queue:work`
- Review Laravel logs for job failures
- Verify database connectivity

#### High Error Rate
- Validate CSV template compliance
- Check data format consistency  
- Review parent email uniqueness
- Verify class names exist in school

#### Parent Account Issues
- Confirm email addresses are valid and unique
- Check if parent users already exist in system
- Review user creation permissions

## Advanced Configuration

### Queue Configuration
```php
// config/queue.php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 7200, // 2 hours for large imports
    ],
],
```

### Import Settings
```php
// In ProcessStudentImport job
protected $chunkSize = 100; // Rows processed per batch
protected $timeout = 3600;  // 1 hour job timeout
```

### File Storage
```php
// config/filesystems.php - ensure bulk_import files are stored properly
'disks' => [
    's3' => [
        // S3 configuration for production
    ],
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
    ],
],
```

## Conclusion

The enhanced bulk student import system provides a comprehensive solution for schools to efficiently import students along with complete family information. The system handles the complexity of user account creation, relationship management, and error handling while providing clear feedback and monitoring capabilities.

For questions or issues, refer to the API documentation or contact the development team.
