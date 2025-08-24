# Class Promotion Mapping API Documentation

## Overview

The Class Promotion Mapping system allows schools to define predefined promotion paths for classes. When a class has a `promotes_to_class_id` set, bulk promotion operations can automatically determine the target class for students without requiring explicit target class specification.

## Database Changes

### New Column: `promotes_to_class_id`

Added to the `classes` table:
- **Type**: `foreignId` (nullable)
- **References**: `classes.id`
- **Purpose**: Defines the default promotion target for a class
- **Constraint**: `onDelete('set null')` - if target class is deleted, this field becomes null

## Model Updates

### ClassRoom Model

Added relationships:
```php
public function promotesTo()
{
    return $this->belongsTo(ClassRoom::class, 'promotes_to_class_id');
}

public function promotesFrom()
{
    return $this->hasMany(ClassRoom::class, 'promotes_to_class_id');
}
```

Added to `$fillable`: `promotes_to_class_id`

## API Endpoints

### 1. Set Promotion Mapping

**PUT** `/api/classes/{id}/promotion-mapping`

Sets or updates the promotion mapping for a specific class.

**Request Body:**
```json
{
    "promotes_to_class_id": 15
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Class promotion mapping updated successfully",
    "data": {
        "id": 12,
        "name": "Grade 5A",
        "grade_level": 5,
        "promotes_to_class_id": 15,
        "promotes_to": {
            "id": 15,
            "name": "Grade 6A",
            "grade_level": 6
        }
    }
}
```

**Validation Rules:**
- `promotes_to_class_id` must exist in classes table (same school)
- Target class must have higher grade level than source class
- Class cannot promote to itself

### 2. Get Classes with Promotion Mappings

**GET** `/api/classes/promotion-mappings`

Retrieves all classes grouped by grade level with their promotion mappings.

**Response:**
```json
{
    "status": "success",
    "message": "Classes with promotion mappings retrieved successfully",
    "data": {
        "classes_by_grade": [
            {
                "grade_level": 1,
                "classes": [
                    {
                        "id": 1,
                        "name": "Grade 1A",
                        "grade_level": 1,
                        "promotes_to_class_id": 5,
                        "promotes_to": {
                            "id": 5,
                            "name": "Grade 2A",
                            "grade_level": 2
                        }
                    }
                ]
            }
        ],
        "total_classes": 12
    }
}
```

## Updated Bulk Promotion API

### Enhanced Bulk Promotion Request

The bulk promotion API now supports automatic target class resolution:

**POST** `/api/promotions/bulk-evaluate`

**Option 1: With explicit target classes (existing functionality)**
```json
{
    "academic_year_id": 1,
    "class_ids": [1, 2, 3],
    "target_class_ids": [5, 6, 7]
}
```

**Option 2: Using predefined promotion mappings (new functionality)**
```json
{
    "academic_year_id": 1,
    "class_ids": [1, 2, 3]
}
```

In Option 2, the system will:
1. Check each source class for `promotes_to_class_id`
2. Use that as the target class for students in that class
3. Fall back to existing promotion criteria logic if no mapping exists

### Validation Updates

The `BulkPromotionRequest` now validates:
- If `target_class_ids` is not provided, all source classes must have `promotes_to_class_id` set
- If some classes don't have promotion mappings, `target_class_ids` becomes required
- Clear error messages indicate which classes need promotion mappings

## Class Creation/Update APIs

### Create Class

**POST** `/api/classes`

```json
{
    "name": "Grade 1A",
    "grade_level": 1,
    "section": "A",
    "capacity": 30,
    "class_teacher_id": 5,
    "promotes_to_class_id": 15,
    "description": "Grade 1 Section A"
}
```

### Update Class

**PUT** `/api/classes/{id}`

Same fields as create, including `promotes_to_class_id`.

## PromotionService Updates

### Enhanced Target Class Resolution

The `getTargetClassForStudent()` method now:

1. **First**: Checks if explicit `target_class_ids` are provided
2. **Second**: If not, checks if current class has `promotes_to_class_id`
3. **Third**: Falls back to existing promotion criteria logic
4. **Last**: Returns null if no target can be determined

```php
private function getTargetClassForStudent($currentClass, $targetClassIds, $schoolId)
{
    // If no specific target classes provided, check if class has predefined promotion path
    if (empty($targetClassIds)) {
        if ($currentClass->promotes_to_class_id) {
            return ClassRoom::find($currentClass->promotes_to_class_id);
        }
        return null;
    }
    
    // Existing logic for explicit target class mapping...
}
```

## Common Use Cases

### 1. Simple Grade Progression

Most common scenario: Grade 1 → Grade 2, Grade 2 → Grade 3, etc.

Set up once during class creation:
- Grade 1A → Grade 2A
- Grade 1B → Grade 2B
- Grade 2A → Grade 3A

Then bulk promotions only need:
```json
{
    "academic_year_id": 1,
    "class_ids": [1, 2, 3, 4, 5]
}
```

### 2. Stream/Section Changes

Handle transitions like:
- Grade 10 General → Grade 11 Science
- Grade 10 General → Grade 11 Commerce

### 3. Mixed Scenarios

Some classes with mappings, others requiring explicit targets:
```json
{
    "academic_year_id": 1,
    "class_ids": [1, 2, 3],
    "target_class_ids": [7, 8, 9]
}
```

Classes 1 & 2 use their mappings, class 3 gets explicit target.

## Error Handling

### Validation Errors

```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "target_class_ids": [
            "Target classes are required because the following classes don't have predefined promotion paths: Grade 5A, Grade 5B"
        ]
    }
}
```

### Business Logic Errors

```json
{
    "status": "error",
    "message": "Target class must have a higher grade level"
}
```

## Benefits

1. **Simplified Bulk Operations**: No need to specify target classes for standard progressions
2. **Reduced Errors**: Predefined mappings prevent incorrect class assignments
3. **Flexible**: Still supports explicit target specification when needed
4. **Intuitive**: Teachers set up promotion paths when creating classes
5. **Maintainable**: Clear relationships visible in class management UI

## Migration Guide

### For Existing Schools

1. **Review Current Classes**: Identify standard promotion patterns
2. **Set Mappings**: Use the promotion mapping API to set `promotes_to_class_id` for standard progressions
3. **Test Bulk Promotions**: Verify simplified bulk promotion requests work correctly
4. **Update Workflows**: Educate staff on new simplified promotion process

### For New Schools

1. **Plan Class Structure**: Design grade progression before creating classes
2. **Set Mappings During Creation**: Include `promotes_to_class_id` when creating classes
3. **Verify Setup**: Use the promotion mappings overview API to confirm structure

This system provides a perfect balance of automation for common cases while maintaining flexibility for complex promotion scenarios.
