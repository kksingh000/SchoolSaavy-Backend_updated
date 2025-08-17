# Academic Year Middleware Integration Guide

## 📝 Overview

The `InjectSchoolData` middleware has been enhanced to automatically inject the current academic year data into all authenticated requests.

## 🔧 What's Injected

The middleware now injects the following data into every request:

### Request Parameters (Accessible via `$request->input()`)
- `school_id` - The user's school ID
- `academic_year_id` - Current academic year ID for the school
- `current_academic_year` - Current academic year label (e.g., "2024-25")
- `created_by` - The authenticated user's ID

### Request Attributes (Accessible via `$request->attributes->get()`)
- `currentAcademicYearModel` - The full AcademicYear model instance

## 🎯 Usage in Controllers

### Using BaseController Helper Methods

```php
class StudentController extends BaseController 
{
    public function index(Request $request) 
    {
        // Get current academic year ID
        $academicYearId = $this->getCurrentAcademicYearId($request);
        
        // Get current academic year label
        $academicYear = $this->getCurrentAcademicYear($request);
        
        // Get the full model
        $academicYearModel = $this->getCurrentAcademicYearModel($request);
        
        // Get school ID
        $schoolId = $this->getSchoolId($request);
        
        // Use in queries
        $students = Student::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->get();
    }
}
```

### Direct Request Access

```php
class AssignmentController extends Controller 
{
    public function store(Request $request) 
    {
        $data = $request->validated();
        
        // These are automatically available
        $data['school_id'] = $request->input('school_id');
        $data['academic_year_id'] = $request->input('academic_year_id');
        $data['created_by'] = $request->input('created_by');
        
        Assignment::create($data);
    }
}
```

## 🏗️ Service Layer Usage

Services can also access the request data:

```php
class AttendanceService extends BaseService 
{
    public function markAttendance(array $data) 
    {
        $request = request();
        
        $attendance = Attendance::create([
            'school_id' => $request->input('school_id'),
            'academic_year_id' => $request->input('academic_year_id'),
            'student_id' => $data['student_id'],
            'date' => $data['date'],
            'status' => $data['status']
        ]);
        
        return $attendance;
    }
}
```

## 🔍 Academic Year Logic

The middleware:

1. **Identifies the school** based on user type:
   - Admin/School Admin: `user->schoolAdmin->school_id`
   - Teacher: `user->teacher->school_id`  
   - Parent: `user->parent->students->first()->school_id`

2. **Finds current academic year** using:
   ```php
   AcademicYear::forSchool($schoolId)
       ->current()      // where is_current = true
       ->active()       // where status = 'active'
       ->first();
   ```

3. **Injects data** into every request automatically

## ⚡ Performance Considerations

- The academic year lookup is cached by Laravel's query cache
- Only one additional query per request for academic year data
- All data is injected once and reused throughout the request lifecycle

## 🛡️ Security

- All academic year data is properly scoped to the user's school
- Multi-tenant isolation maintained via `school_id` filtering
- No cross-school data leakage possible

## 📊 Example Usage Patterns

### Attendance with Academic Year
```php
$attendance = Attendance::create([
    'school_id' => $request->input('school_id'),
    'academic_year_id' => $request->input('academic_year_id'),
    'student_id' => $studentId,
    'date' => now()->format('Y-m-d'),
    'status' => 'present'
]);
```

### Assignments for Current Year
```php
$assignments = Assignment::where('school_id', $request->input('school_id'))
    ->where('academic_year_id', $request->input('academic_year_id'))
    ->where('class_id', $classId)
    ->get();
```

### Assessment Results
```php
$results = AssessmentResult::whereHas('assessment', function($q) use ($request) {
    $q->where('school_id', $request->input('school_id'))
      ->where('academic_year_id', $request->input('academic_year_id'));
})->get();
```

## 🚨 Important Notes

1. **Academic Year Required**: If no current academic year exists for a school, `academic_year_id` will be `null`
2. **Fallback Logic**: Controllers should handle cases where no current academic year exists
3. **Migration Impact**: All historical data should be linked to appropriate academic years
4. **Multi-Year Support**: Schools can have multiple academic years, but only one marked as "current"

This enhancement ensures all modules automatically work within the correct academic year context!
