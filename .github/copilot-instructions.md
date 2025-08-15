# SchoolSavvy SaaS - AI Coding Agent Instructions

## 🎯 Project Overview

SchoolSavvy is a **production-ready SaaS platform** built with Laravel 12 & PHP 8.3 for comprehensive school management. This is a **multi-tenant modular system** where schools purchase and activate specific modules. The codebase is **85% complete** with a sophisticated architecture optimized for performance and scalability.

### 🏗️ Core Architecture

- **Framework**: Laravel 12 with PHP 8.3, MySQL 8.0, Redis caching
- **Authentication**: Laravel Sanctum with multi-role access (Super Admin, School Admin, Teacher, Parent, Student)
- **Deployment**: Docker with RoadRunner (Octane) for high performance
- **API Design**: 100+ REST endpoints with comprehensive validation
- **Database**: Multi-tenant with school_id isolation, 50+ optimized tables

---

## 🧩 Code Architecture Patterns

### 🎨 MVC + Service Layer Pattern

**CRITICAL**: Always follow the established Service-Controller-Model pattern:

```php
// Controllers handle HTTP logic only
class StudentController extends BaseController {
    public function __construct(private StudentService $studentService) {}
    
    public function store(StudentRequest $request) {
        $student = $this->studentService->create($request->validated());
        return $this->successResponse($student, 'Student created successfully');
    }
}

// Services contain ALL business logic
class StudentService extends BaseService {
    protected function initializeModel() {
        $this->model = Student::class;
    }
    
    public function create(array $data) {
        // Business logic, validation, relationships here
        $data['school_id'] = auth()->user()->getSchool()->id;
        return $this->model::create($data);
    }
}
```

### 🔐 Multi-Tenant Security Pattern

**MANDATORY**: Every database query MUST include school isolation:

```php
// ✅ CORRECT - Always filter by school_id
Student::where('school_id', auth()->user()->getSchool()->id)->get();

// ❌ WRONG - Never query without school isolation
Student::all(); // This will leak data between schools
```

### 🛡️ BaseController & BaseService Usage

**Always extend** base classes for consistent functionality:

```php
// Controllers - use BaseController for common responses
class MyController extends BaseController {
    // Inherit: successResponse(), errorResponse(), checkModuleAccess()
}

// Services - use BaseService for CRUD operations
class MyService extends BaseService {
    // Inherit: getAll(), find(), create(), update(), delete()
}
```

---

## 📡 API Conventions

### 🎯 Response Format Standard

**ALL API responses** must follow this format:

```php
// Success Response
return $this->successResponse($data, 'Operation successful', 200);
// Returns: {"status": "success", "message": "...", "data": {...}}

// Error Response  
return $this->errorResponse('Error message', $validationErrors, 400);
// Returns: {"status": "error", "message": "...", "errors": {...}}
```

### 🔄 API Route Structure

Follow the established route organization in `routes/api.php`:

```php
// Group routes by feature with middleware
Route::middleware(['auth:sanctum', 'inject.school'])->group(function () {
    // Module-specific routes
    Route::prefix('students')->group(function () {
        Route::get('/', [StudentController::class, 'index']);
        Route::post('/', [StudentController::class, 'store']);
        // Use apiResource for standard CRUD
        Route::apiResource('students', StudentController::class);
    });
});
```

### 📝 Request Validation Pattern

**Always create** Form Request classes for validation:

```php
class StudentRequest extends FormRequest {
    public function rules() {
        return [
            'first_name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email',
            'class_id' => 'required|exists:classes,id'
        ];
    }
    
    public function messages() {
        return [
            'first_name.required' => 'Student first name is required',
        ];
    }
}
```

---

## 🗄️ Database & Model Patterns

### 🔗 Model Relationships

**Standard relationship patterns** used throughout:

```php
class Student extends Model {
    use HasFactory, SoftDeletes;
    
    protected $fillable = ['school_id', 'first_name', 'last_name', 'email'];
    
    // Always include school relationship
    public function school() {
        return $this->belongsTo(School::class);
    }
    
    // Many-to-many with pivot data
    public function classes() {
        return $this->belongsToMany(ClassRoom::class, 'class_student')
            ->withPivot(['enrollment_date', 'is_active'])
            ->withTimestamps();
    }
    
    // Use consistent scope naming
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }
    
    public function scopeForSchool($query, $schoolId) {
        return $query->where('school_id', $schoolId);
    }
}
```

### 📊 Migration Conventions

Follow established table naming and structure:

```php
Schema::create('students', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->onDelete('cascade');
    $table->string('admission_number')->unique();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email')->unique();
    $table->enum('gender', ['male', 'female', 'other']);
    $table->date('date_of_birth');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
    
    // Always add indexes for performance
    $table->index(['school_id', 'is_active']);
    $table->index('admission_number');
});
```

---

## 🛠️ Module System

### 🔧 Module Access Control

**CRITICAL**: Always check module access before operations:

```php
class StudentController extends BaseController {
    public function index() {
        // Check if school has student-management module active
        if (!$this->checkModuleAccess('student-management')) {
            return $this->moduleAccessDenied();
        }
        
        $students = $this->studentService->getAll();
        return $this->successResponse($students);
    }
}
```

### 📦 Available Modules

Current production modules (check `ModuleSeeder.php`):
- `student-management` - Student CRUD, profiles, academic records
- `class-management` - Class organization, sections, teacher assignments  
- `attendance-system` - Daily attendance, bulk operations, reports
- `assignment-management` - Complete assignment workflow with grading
- `assessment-system` - Dynamic test types, result management
- `timetable-management` - Schedule creation, conflict detection
- `event-management` - School events, calendar, acknowledgments

---

## 🔒 Authentication & Authorization

### 👤 User Types & Roles

The system supports 5 user types with specific access patterns:

```php
// User types (check User model)
'super_admin' // Platform administrator
'admin'       // School administrator  
'teacher'     // Teaching staff
'parent'      // Student parents/guardians
'student'     // Students

// Role-based middleware usage
Route::middleware('user.type:teacher,admin')->group(function () {
    // Teacher and admin only routes
});
```

### 🔑 Authentication Flow

**Standard auth pattern** using Laravel Sanctum:

```php
// Login returns user data with school context
POST /api/auth/login
{
    "email": "user@school.com",
    "password": "password123"
}

// Response includes role-specific data
{
    "status": "success",
    "data": {
        "user": {...},
        "token": "...",
        "school": {...},
        "children": [...] // Only for parents
    }
}
```

---

## 📈 Performance & Caching

### ⚡ Redis Caching Pattern

**Use Redis** for expensive operations:

```php
// Cache parent statistics (5-minute cache)
$cacheKey = "parent_stats_{$parentId}_{$studentId}";
$statistics = Cache::remember($cacheKey, 300, function () use ($parentId, $studentId) {
    return $this->calculateStudentStatistics($parentId, $studentId);
});

// Clear cache when data changes
Cache::forget("parent_stats_{$parentId}_{$studentId}");
```

### 🔄 Query Optimization

**Always optimize** database queries:

```php
// ✅ GOOD - Eager load relationships
$students = Student::with(['class', 'parents'])
    ->where('school_id', $schoolId)
    ->paginate(15);

// ✅ GOOD - Use select() for specific fields
$students = Student::select('id', 'first_name', 'last_name')
    ->forSchool($schoolId)
    ->active()
    ->get();

// ❌ BAD - N+1 query problem
foreach ($students as $student) {
    $student->class; // This creates N+1 queries
}
```

---

## 🧪 Testing & Quality

### 📋 Validation Standards

**Always validate** inputs comprehensively:

```php
class AssignmentRequest extends FormRequest {
    public function rules() {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'due_date' => 'required|date|after:today',
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx|max:10240'
        ];
    }
}
```

### 🐛 Error Handling

**Consistent error handling** throughout:

```php
try {
    $result = $this->service->processData($data);
    return $this->successResponse($result, 'Data processed successfully');
} catch (ValidationException $e) {
    return $this->errorResponse('Validation failed', $e->errors(), 422);
} catch (ModelNotFoundException $e) {
    return $this->errorResponse('Record not found', null, 404);
} catch (\Exception $e) {
    Log::error('Unexpected error: ' . $e->getMessage());
    return $this->errorResponse('An unexpected error occurred', null, 500);
}
```

---

## 📁 File Structure Conventions

### 📂 Directory Organization

Follow the established structure:

```
app/
├── Http/
│   ├── Controllers/        # HTTP request handling only
│   ├── Middleware/         # Authentication, authorization, data injection
│   ├── Requests/           # Form validation classes
│   └── Resources/          # API response transformation
├── Models/                 # Eloquent models with relationships
├── Services/               # Business logic layer
└── Jobs/                   # Background job processing

database/
├── migrations/             # Schema definitions
├── seeders/                # Demo and initial data
├── factories/              # Test data generation
└── schema/                 # Database documentation
```

### 📝 File Naming

**Consistent naming** across the codebase:

```
StudentController.php       # HTTP controllers
StudentService.php         # Business logic services  
StudentRequest.php         # Form validation
Student.php                # Eloquent models
CreateStudentsTable.php    # Database migrations
StudentSeeder.php          # Data seeders
StudentFactory.php         # Model factories
```

---

## 🚀 Development Workflow

### 🔄 Feature Development Process

1. **Create migration** for database changes
2. **Update/create model** with relationships and scopes
3. **Create service class** with business logic
4. **Create form request** for validation
5. **Create/update controller** for HTTP handling
6. **Add routes** to `api.php` with proper middleware
7. **Test endpoints** with proper school isolation

### 📊 Assessment System (Advanced)

**Special attention** to the sophisticated assessment system:

```php
// Assessment types are configurable per school
$assessmentType = AssessmentType::create([
    'school_id' => $schoolId,
    'name' => 'UT',
    'display_name' => 'Unit Test',
    'frequency' => 'monthly',
    'weightage_percentage' => 25,
    'settings' => [
        'allow_retakes' => false,
        'time_limit_minutes' => 90,
        'auto_publish_results' => true
    ]
]);

// Results can be bulk entered and selectively published
$results = AssessmentResult::create([
    'assessment_id' => $assessmentId,
    'student_id' => $studentId,
    'marks_obtained' => 85,
    'total_marks' => 100,
    'percentage' => 85.0,
    'grade' => 'A',
    'result_status' => 'pass'
]);
```

---

## ⚠️ Critical Guidelines

### 🚨 Security Rules

1. **NEVER** query without school_id isolation in multi-tenant context
2. **ALWAYS** validate user permissions for module access
3. **ALWAYS** sanitize file uploads and validate file types
4. **NEVER** expose sensitive data in API responses
5. **ALWAYS** use rate limiting for public endpoints

### 📏 Code Quality Standards

1. **Use type hints** for all method parameters and return types
2. **Write descriptive method names** that explain the action
3. **Keep controllers thin** - move logic to services
4. **Use Form Requests** for all validation
5. **Add proper PHPDoc** comments for complex methods

### 🔧 Performance Requirements

1. **Use pagination** for all list endpoints (default 15 items)
2. **Implement caching** for expensive calculations
3. **Eager load relationships** to prevent N+1 queries
4. **Use database indexes** for frequently queried fields
5. **Optimize API responses** by selecting only needed fields

---

## 📱 Mobile API Integration

### 📞 Parent Mobile APIs

**Specialized endpoints** for parent mobile app:

```php
// Parent-specific routes with user type middleware
Route::prefix('parent')->middleware('user.type:parent')->group(function () {
    Route::get('children', [ParentController::class, 'getChildren']);
    Route::post('student/statistics', [ParentController::class, 'getStudentStatistics']);
    Route::post('student/attendance', [ParentController::class, 'getStudentAttendance']);
    Route::post('student/assignments', [ParentController::class, 'getStudentAssignments']);
});
```

### 🔄 Authentication Enhanced for Mobile

Login API returns **comprehensive user context**:

```php
// Enhanced login response for parents includes student data
{
    "user": {...},
    "token": "...",
    "school": {...},
    "children": [
        {
            "id": 1,
            "name": "John Doe",
            "class": "Grade 5A",
            "statistics": {
                "attendance_percentage": 95.5,
                "assignments_completed": 23,
                "pending_assignments": 2,
                "average_grade": "A"
            }
        }
    ]
}
```

---

## 🛠️ Development Environment

### 🐳 Docker Development

The project uses **Docker with RoadRunner** for production performance:

```bash
# Start development environment
docker-compose up -d

# The application runs on Laravel Octane with RoadRunner
# - Main app: http://localhost:8080
# - MySQL: localhost:3306  
# - Redis: localhost:6379
```

### 📦 Package Management

**Key dependencies** to be aware of:

```json
{
    "php": "^8.2",
    "laravel/framework": "^12.0", 
    "laravel/sanctum": "^4.0",        // API authentication
    "laravel/octane": "^2.12",        // High performance
    "intervention/image": "^3.11",    // Image processing
    "aws/aws-sdk-php": "^3.352",      // File storage
    "predis/predis": "^3.2"           // Redis integration
}
```

---

## 📚 Quick Reference

### 🎯 Most Important Files to Understand

1. `app/Http/Controllers/BaseController.php` - Common controller functionality
2. `app/Services/BaseService.php` - Service layer foundation
3. `routes/api.php` - Complete API structure
4. `app/Models/User.php` - Multi-role user system
5. `app/Services/ParentService.php` - Example of complex business logic
6. `database/seeders/DatabaseSeeder.php` - Data structure understanding

### 🔗 Key Relationships to Remember

```php
School -> hasMany -> Students, Teachers, Classes, Assessments
Student -> belongsToMany -> Classes, Parents
Class -> hasMany -> Students, Assignments, Assessments  
Teacher -> hasMany -> Classes, Assignments, Assessments
Parent -> belongsToMany -> Students
Assessment -> hasMany -> AssessmentResults
```

### 🎮 Common Operations

```php
// Get current user's school
$school = auth()->user()->getSchool();

// Check module access
if (!$this->checkModuleAccess('student-management')) {
    return $this->moduleAccessDenied();
}

// Standard paginated response
$students = Student::forSchool($schoolId)->active()->paginate(15);
return $this->successResponse($students);

// Cache expensive operation
$stats = Cache::remember("stats_{$id}", 300, fn() => $this->calculateStats($id));
```

---

**🎓 Remember**: SchoolSavvy is a production-ready SaaS platform with sophisticated multi-tenant architecture. Always prioritize security, performance, and maintainability in your code contributions. Follow the established patterns and this codebase will continue to scale beautifully!
