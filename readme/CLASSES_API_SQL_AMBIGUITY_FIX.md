# SQL Ambiguous Column Fix for Classes API

## Problem
The optimized classes API was throwing an SQL error:
```
SQLSTATE[23000]: Integrity constraint violation: 1052 Column 'is_active' in WHERE is ambiguous
```

This occurred because the `is_active` column exists in both:
- `students` table
- `class_student` pivot table

## Root Cause
In the `withCount` query for student counting, the ORM couldn't determine which `is_active` column to use when filtering active students.

## Solution Applied

### 1. Used Existing `activeStudents` Relationship
Instead of manually filtering with `where('is_active', true)`, used the existing `activeStudents` relationship defined in the ClassRoom model:

```php
// Before (ambiguous):
$query->withCount([
    'students as student_count' => function ($query) {
        $query->where('is_active', true); // Ambiguous!
    }
]);

// After (fixed):
$query->withCount([
    'activeStudents as student_count'
]);
```

### 2. Added Table Prefixes for Clarity
Updated all column references to include table prefixes to prevent future ambiguity:

```php
// Search functionality:
$q->where('classes.name', 'like', "%{$search}%")
  ->orWhere('classes.section', 'like', "%{$search}%")
  ->orWhere('classes.grade_level', 'like', "%{$search}%")

// Filter application:
$query->where('classes.' . $field, $value);

// Ordering:
$query->orderBy('classes.name')->orderBy('classes.section')->orderBy('classes.id');

// School ID filter:
$query->where('classes.school_id', $schoolId);
```

## Benefits of the Fix

1. **Eliminates SQL Errors**: No more ambiguous column references
2. **Uses Existing Model Logic**: Leverages the already-defined `activeStudents` relationship
3. **Future-Proof**: Table prefixes prevent similar issues with other columns
4. **Maintains Performance**: No impact on the optimization gains
5. **Cleaner Code**: Uses Laravel's relationship definitions instead of manual filtering

## Files Modified
- `app/Services/ClassService.php` - Fixed the `getAll()` method

## Testing
The fix maintains all existing functionality while resolving the SQL constraint violation error. The API will now work correctly with the optimized performance improvements.
