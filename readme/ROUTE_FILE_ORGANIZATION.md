# Route File Organization

## Overview

The API routes have been organized into separate files for better maintainability and easier management. Each file handles routes for specific user roles or functionality.

## File Structure

```
routes/
├── api.php          # Main route file with authentication and common routes
├── admin.php        # Admin and general school management routes
├── parent.php       # Parent mobile app specific routes
├── teacher.php      # Teacher specific routes
└── superadmin.php   # Super admin platform management routes
```

## Route Files Breakdown

### 1. `routes/api.php` (Main Entry Point)
**Purpose**: Contains authentication routes, public routes, and includes other route files

**Key Routes**:
- `/api/health` - Health check endpoint
- `/api/auth/*` - Authentication endpoints (login, logout, refresh)
- `/api/contact/*` - Public contact form routes
- `/api/school` - Basic school info endpoint

**Includes**: Automatically includes all other route files using `require __DIR__.'/filename.php'`

### 2. `routes/admin.php` (School Administration)
**Purpose**: Routes for school administrators, teachers, and general school management

**Middleware Applied**: 
- `auth:sanctum` - Authentication required
- `school.status` - School must be active
- `inject.school` - Inject school context

**Key Route Groups**:
- `/api/dashboard` - Admin dashboard
- `/api/modules/*` - Module management
- `/api/students/*` - Student management with caching
- `/api/classes/*` - Class management with intelligent caching
- `/api/teachers/*` - Teacher management
- `/api/attendance/*` - Attendance system
- `/api/assignments/*` - Assignment management
- `/api/assessments/*` - Assessment system
- `/api/gallery/*` - Gallery management
- `/api/events/*` - Event management
- `/api/timetable/*` - Timetable management
- `/api/subjects/*` - Subject management
- `/api/uploads/*` - File upload system
- `/api/academic-years/*` - Academic year management
- `/api/promotions/*` - Student promotion system
- `/api/school-settings/*` - School configuration
- `/api/admission-number/*` - Admission number generation
- `/api/roll-number/*` - Roll number management

### 3. `routes/parent.php` (Parent Mobile App)
**Purpose**: Specialized routes for the parent mobile application

**Middleware Applied**:
- `auth:sanctum` - Authentication required
- `school.status` - School must be active
- `inject.school` - Inject school context
- `user.type:parent` - Only parents can access these routes

**Key Routes**:
- `/api/parent/children` - Get parent's children
- `/api/parent/student/statistics` - Student performance statistics
- `/api/parent/student/attendance` - Student attendance data
- `/api/parent/student/assignments` - Student assignments
- `/api/parent/student/gallery/*` - Student gallery access

### 4. `routes/teacher.php` (Teacher Specific)
**Purpose**: Teacher-specific functionality and teacher dashboard routes

**Middleware Applied**:
- `auth:sanctum` - Authentication required
- `school.status` - School must be active
- `inject.school` - Inject school context
- `user.type:teacher,admin` - Teachers and admins can access

**Key Route Groups**:
- `/api/teacher/dashboard-stats` - Teacher dashboard statistics
- `/api/teacher/classes/*` - Teacher's class management
- `/api/teacher/assignments/*` - Teacher assignment management
- `/api/teacher/attendance/*` - Teacher attendance marking
- `/api/teacher/assessments/*` - Teacher assessment management
- `/api/teacher/timetable/*` - Teacher schedule management

### 5. `routes/superadmin.php` (Platform Management)
**Purpose**: Platform-level management for super administrators

**Middleware Applied**:
- `auth:sanctum` - Authentication required
- `super.admin` - Only super admins can access

**Key Route Groups**:
- `/api/super-admin/schools/*` - School management
- `/api/super-admin/analytics/*` - Platform analytics and reporting

## Benefits of This Structure

### 1. **Better Organization**
- Routes are logically grouped by user role and functionality
- Easier to find and modify specific routes
- Clear separation of concerns

### 2. **Improved Maintainability**
- Changes to parent routes don't affect admin routes
- Easier to add new features for specific user types
- Reduced merge conflicts when multiple developers work on different features

### 3. **Enhanced Security**
- Role-specific middleware applied at file level
- Clearer access control boundaries
- Easier to audit permissions

### 4. **Scalability**
- Easy to add new route files for new user roles
- Can implement different caching strategies per user type
- Modular structure supports feature-based development

## Usage Examples

### Adding New Admin Routes
Edit `routes/admin.php` and add your routes within the existing middleware group:

```php
// In routes/admin.php
Route::prefix('new-feature')->group(function () {
    Route::get('/', [NewFeatureController::class, 'index']);
    Route::post('/', [NewFeatureController::class, 'store']);
});
```

### Adding New Parent Routes
Edit `routes/parent.php` and add your routes within the parent middleware group:

```php
// In routes/parent.php
Route::post('student/new-feature', [ParentController::class, 'getStudentNewFeature']);
```

### Adding New Teacher Routes
Edit `routes/teacher.php` and add your routes within the teacher middleware group:

```php
// In routes/teacher.php
Route::prefix('teacher/new-feature')->group(function () {
    Route::get('/', [TeacherController::class, 'getNewFeature']);
});
```

## Important Notes

1. **Route Loading**: All route files are automatically loaded via `require` statements in `api.php`

2. **Middleware Inheritance**: Routes in included files inherit the middleware from the group they're placed in

3. **Route Conflicts**: Be careful of route naming conflicts between files. Use appropriate prefixes.

4. **Caching**: Admin routes include intelligent caching strategies. Consider caching when adding new routes.

5. **Testing**: When adding new routes, test them with appropriate user roles and middleware.

## Migration from Old Structure

If you need to move existing routes:

1. **Identify the route's user role/purpose**
2. **Cut the route from `api.php`**
3. **Paste it in the appropriate new file**
4. **Ensure proper middleware is applied**
5. **Test the route functionality**

This structure provides a solid foundation for the SchoolSavvy platform's continued growth and development.
