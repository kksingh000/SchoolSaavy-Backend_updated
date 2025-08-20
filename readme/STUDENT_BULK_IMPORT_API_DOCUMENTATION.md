# Student Bulk Import API Documentation

## Overview
The Student Bulk Import feature allows school administrators to import multiple students at once using CSV files. The process uses the existing file upload API for file handling and provides real-time progress tracking.

## Complete Workflow

### Step 1: Upload CSV File
First, upload your CSV file using the existing file upload API with the dedicated `bulk_import` type:

```bash
POST /api/files/upload/single
Content-Type: multipart/form-data

file: [your-csv-file]
type: bulk_import
```

> **Note**: The `bulk_import` type only accepts CSV, XLS, and XLSX files. For student imports, CSV format is recommended for better performance and compatibility.

**Response:**
```json
{
    "status": "success",
    "message": "File uploaded successfully",
    "data": {
        "path": "uploads/bulk_import/1692123456_students.csv",
        "url": "https://your-bucket.s3.amazonaws.com/uploads/bulk_import/1692123456_students.csv",
        "name": "students.csv",
        "size": 15360,
        "type": "text/csv"
    }
}
```

### Step 2: Initiate Import
Use the file path from Step 1 to start the import process:

```bash
POST /api/students/import/
Content-Type: application/json

{
    "file_path": "uploads/bulk_import/1692123456_students.csv",
    "file_name": "students.csv"
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Student import initiated successfully. Processing in background.",
    "data": {
        "id": 1,
        "file_name": "students.csv",
        "file_size": 15360,
        "file_size_formatted": "15.0 KB",
        "status": "pending",
        "total_rows": 0,
        "processed_rows": 0,
        "success_count": 0,
        "failed_count": 0,
        "progress_percentage": 0,
        "has_errors": false,
        "created_at": "2025-08-19T22:30:00.000000Z",
        "user": {
            "id": 1,
            "name": "Admin User"
        }
    }
}
```

## File Upload Types

The SchoolSavvy platform supports the following upload types:

- `assignment` - Student assignments and homework files
- `profile` - User profile images
- `communication` - Communication-related documents
- `event` - Event-related files and images  
- `general` - General-purpose file uploads
- `media` - Media files for gallery and content
- `bulk_import` - CSV/Excel files for bulk data imports (CSV, XLS, XLSX only)

For student imports, always use `type: "bulk_import"` to ensure proper file validation and organization.

## API Endpoints

### 1. Download CSV Template
```bash
GET /api/students/import/template
```
Downloads a pre-formatted CSV template with sample data and correct headers.

### 2. Import Students
```bash
POST /api/students/import/
```
**Body:**
```json
{
    "file_path": "string (required) - Path from file upload API",
    "file_name": "string (required) - Original filename"
}
```

### 3. Get Import History
```bash
GET /api/students/import/
```
**Query Parameters:**
- `per_page` (optional): Number of imports per page (default: 15)

### 4. Get Import Details
```bash
GET /api/students/import/{id}
```

### 5. Get Import Errors
```bash
GET /api/students/import/{id}/errors
```
**Query Parameters:**
- `per_page` (optional): Number of errors per page (default: 50)

### 6. Cancel Import
```bash
POST /api/students/import/{id}/cancel
```

### 7. Delete Import
```bash
DELETE /api/students/import/{id}
```

## CSV File Format

### Required Headers (in exact order):
- `admission_number` - Unique identifier for the student
- `first_name` - Student's first name
- `last_name` - Student's last name
- `date_of_birth` - Format: YYYY-MM-DD
- `gender` - Values: male, female, other
- `admission_date` - Format: YYYY-MM-DD
- `blood_group` - Values: A+, A-, B+, B-, O+, O-, AB+, AB-
- `address` - Student's address
- `phone` - Contact number (optional)
- `class_name` - Name of the class (must exist in system)
- `class_section` - Section of the class
- `roll_number` - Student's roll number in the class

### Sample CSV Content:
```csv
admission_number,first_name,last_name,date_of_birth,gender,admission_date,blood_group,address,phone,class_name,class_section,roll_number
STU001,John,Doe,2015-05-15,male,2024-04-01,A+,123 Main Street,9876543210,Grade 1,A,1
STU002,Jane,Smith,2014-08-22,female,2024-04-01,B+,456 Oak Avenue,9876543211,Grade 2,B,2
STU003,Alex,Johnson,2016-01-10,other,2024-04-01,O+,789 Pine Road,9876543212,Grade 3,A,3
```

## Import Status Values

- `pending` - Import is queued for processing
- `processing` - Import is currently being processed
- `completed` - Import finished successfully
- `failed` - Import failed due to an error
- `cancelled` - Import was cancelled by user

## Error Handling

The system provides detailed error reporting:

1. **File-level errors**: Invalid file format, missing headers, etc.
2. **Row-level errors**: Invalid data in specific rows (stored in `student_import_errors` table)
3. **Validation errors**: Duplicate admission numbers, invalid dates, missing required fields

## Business Rules

1. **Admission Numbers**: Must be unique within the school
2. **Class Assignment**: Classes must exist in the system (won't auto-create)
3. **Data Validation**: All required fields must be present and valid
4. **School Isolation**: Students are automatically assigned to the authenticated user's school
5. **Duplicate Handling**: Duplicate admission numbers will be rejected

## Performance Considerations

- Files are processed in chunks of 100 rows at a time
- Progress is updated every 50 rows
- Maximum file size: 10MB
- Supports thousands of records per import
- Background processing prevents timeout issues

## Monitoring Import Progress

Poll the import details endpoint to monitor progress:

```bash
GET /api/students/import/{id}
```

**Response includes:**
- `progress_percentage`: 0-100%
- `processed_rows`: Number of rows processed
- `success_count`: Successfully imported students
- `failed_count`: Number of failed rows
- `status`: Current import status

## Example Integration

```javascript
// 1. Upload file
const formData = new FormData();
formData.append('file', csvFile);
formData.append('type', 'bulk_import');

const uploadResponse = await fetch('/api/files/upload/single', {
    method: 'POST',
    body: formData,
    headers: {
        'Authorization': `Bearer ${token}`
    }
});

const uploadData = await uploadResponse.json();

// 2. Start import
const importResponse = await fetch('/api/students/import/', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
        file_path: uploadData.data.path,
        file_name: uploadData.data.name
    })
});

const importData = await importResponse.json();
const importId = importData.data.id;

// 3. Monitor progress
const checkProgress = async () => {
    const response = await fetch(`/api/students/import/${importId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await response.json();
    
    console.log(`Progress: ${data.data.progress_percentage}%`);
    
    if (!data.data.is_completed) {
        setTimeout(checkProgress, 2000); // Check every 2 seconds
    } else {
        console.log(`Import completed! Success: ${data.data.success_count}, Failed: ${data.data.failed_count}`);
    }
};

checkProgress();
```

## Security Features

- All imports are scoped to the authenticated user's school
- File validation prevents malicious uploads
- Background processing prevents resource exhaustion
- Detailed audit trail of all import activities
- Module-based access control (requires 'student-management' module)
