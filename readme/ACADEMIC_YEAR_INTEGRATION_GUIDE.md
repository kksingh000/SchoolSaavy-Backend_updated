# Academic Year Integration Guide

## 📚 Overview

After implementing the academic year system, here's how different modules now work with academic years:

## 🔗 Database Relationships Added

### **Core Tables Updated:**
- ✅ `assignments` - Now has `academic_year_id` 
- ✅ `assessments` - Now has `academic_year_id`
- ✅ `class_schedules` - Now has `academic_year_id`
- ✅ `fee_structures` - Now has `academic_year_id` (plus keeping old `academic_year` for compatibility)
- ✅ `gallery_albums` - Now has `academic_year_id`

### **Student-Class Relationship:**
- ✅ Already had `academic_year_id` support in the pivot table

## 🎯 How to Use Academic Years

### 1. **Creating Records with Academic Year**

```php
// When creating assignments, automatically set current academic year
$assignment = Assignment::create([
    'school_id' => auth()->user()->getSchool()->id,
    'academic_year_id' => AcademicYear::forSchool($schoolId)->current()->first()->id,
    'title' => 'Math Assignment 1',
    'class_id' => 1,
    'subject_id' => 1,
    // ... other fields
]);

// Or get current academic year once and reuse
$currentYear = AcademicYear::forSchool($schoolId)->current()->first();

$assessment = Assessment::create([
    'school_id' => $schoolId,
    'academic_year_id' => $currentYear->id,
    'title' => 'Mid-term Math Test',
    // ... other fields
]);
```

### 2. **Querying Records by Academic Year**

```php
// Get all assignments for current academic year
$currentAssignments = Assignment::currentYear()
    ->forSchool($schoolId)
    ->get();

// Get assignments for specific academic year
$assignments2024 = Assignment::forAcademicYear($academicYearId)
    ->forSchool($schoolId)
    ->get();

// Get assessments for a specific class in current year
$assessments = Assessment::currentYear()
    ->where('class_id', $classId)
    ->forSchool($schoolId)
    ->get();
```

### 3. **Service Layer Integration**

Update your service classes to automatically handle academic years:

```php
class AssignmentService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = Assignment::class;
    }
    
    public function create(array $data)
    {
        // Auto-set current academic year if not provided
        if (!isset($data['academic_year_id'])) {
            $currentYear = AcademicYear::forSchool($data['school_id'])->current()->first();
            $data['academic_year_id'] = $currentYear?->id;
        }
        
        return parent::create($data);
    }
    
    public function getForCurrentYear($schoolId, $classId = null)
    {
        $query = Assignment::currentYear()
            ->where('school_id', $schoolId);
            
        if ($classId) {
            $query->where('class_id', $classId);
        }
        
        return $query->with(['class', 'subject', 'teacher'])->get();
    }
}
```

## 📊 API Endpoint Updates Needed

### **Assignment APIs:**
```php
// In AssignmentController, filter by academic year
public function index(Request $request)
{
    $schoolId = auth()->user()->getSchool()->id;
    
    $query = Assignment::forSchool($schoolId);
    
    // Default to current year, allow filtering by specific year
    $academicYearId = $request->get('academic_year_id');
    if ($academicYearId) {
        $query->forAcademicYear($academicYearId);
    } else {
        $query->currentYear();
    }
    
    $assignments = $query->paginate();
    return $this->successResponse($assignments);
}
```

### **Assessment APIs:**
```php
// Filter assessments by academic year
GET /api/assessments?academic_year_id=1
GET /api/assessments (defaults to current year)

// Assessment results also filtered by year
GET /api/assessments/{id}/results?academic_year_id=1
```

### **Gallery Albums:**
```php
// Get albums for current academic year
GET /api/gallery/albums (current year)
GET /api/gallery/albums?academic_year_id=1 (specific year)

// Useful for yearly events like "Annual Day 2024", "Sports Day 2025"
```

## 🔄 Migration Strategy

### **For Existing Data:**

1. **Set Academic Year for Existing Records:**
```php
// Create a command to populate academic years for existing data
php artisan make:command PopulateAcademicYears

// In the command:
public function handle()
{
    // Update existing assignments
    Assignment::whereNull('academic_year_id')->chunk(100, function ($assignments) {
        foreach ($assignments as $assignment) {
            $currentYear = AcademicYear::forSchool($assignment->school_id)
                ->current()
                ->first();
            
            if ($currentYear) {
                $assignment->update(['academic_year_id' => $currentYear->id]);
            }
        }
    });
}
```

### **Backward Compatibility:**

The system maintains backward compatibility:
- Old queries without academic year filters still work
- Fee structures keep both `academic_year` (string) and `academic_year_id` (foreign key)
- Nullable academic year fields don't break existing functionality

## 📈 Benefits of Academic Year Integration

### **1. Data Organization:**
```php
// Clear separation of data by year
$assignments2024 = Assignment::forAcademicYear($year2024Id)->get();
$assignments2025 = Assignment::forAcademicYear($year2025Id)->get();
```

### **2. Performance:**
```php
// Better query performance with indexes
// Index on (school_id, academic_year_id, class_id) speeds up common queries
```

### **3. Reporting:**
```php
// Easy year-over-year comparisons
$currentYearPerformance = Assessment::currentYear()
    ->with('results')
    ->where('class_id', $classId)
    ->get();
    
$lastYearPerformance = Assessment::forAcademicYear($lastYearId)
    ->with('results')
    ->where('class_id', $classId)  
    ->get();
```

### **4. Promotion Integration:**
```php
// Seamless integration with promotion system
$student->getCurrentClassForYear($currentAcademicYearId);
$student->wasPromotedInYear($previousYearId);
```

## ⚠️ Important Notes

### **Required Updates to Controllers:**

1. **Auto-populate academic year** when creating records
2. **Default to current year** in list endpoints
3. **Allow filtering** by academic year in APIs
4. **Handle year transitions** during promotion periods

### **Frontend Considerations:**

```javascript
// Add academic year selector to forms
<select name="academic_year_id">
  <option value="">Select Academic Year</option>
  {academicYears.map(year => (
    <option value={year.id} selected={year.is_current}>
      {year.display_name}
    </option>
  ))}
</select>

// Default to current year in filters
const currentYear = academicYears.find(year => year.is_current);
```

## 🎓 Summary

**YES, you should add academic year relationships to:**
- ✅ **Assignments** - Different assignments each year
- ✅ **Assessments** - Test results per year
- ✅ **Class Schedules** - Timetables change yearly  
- ✅ **Fee Structures** - Fees structure per academic year
- ✅ **Gallery Albums** - Events can be yearly

**Optional for:**
- 📷 **Gallery Albums** - Implemented as optional
- 📅 **Events** - Could add if events repeat annually

This provides better data organization, performance, and enables powerful year-over-year analytics while maintaining backward compatibility!
