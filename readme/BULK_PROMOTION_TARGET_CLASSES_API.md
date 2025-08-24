# Bulk Promotion with Target Classes API Documentation

## Overview

The bulk promotion system now supports specifying target classes where students should be promoted. This allows schools to control exactly which classes students should be moved to, rather than relying solely on promotion criteria defaults.

## Enhanced Bulk Evaluation API

### POST /api/promotions/bulk-evaluate

This endpoint now accepts `target_class_ids` parameter to specify which classes students should be promoted to.

#### Request Body

```json
{
    "academic_year_id": 1,
    "class_ids": [1, 2, 3],
    "target_class_ids": [4, 5, 6]
}
```

#### Parameters

- `academic_year_id` (required): The academic year for promotion evaluation
- `class_ids` (required): Array of source class IDs (classes to promote students from)  
- `target_class_ids` (required): Array of target class IDs (classes to promote students to)

#### Validation Rules

- Both source and target classes must exist and belong to the current school
- Target classes must have higher grade levels than source classes
- Source and target class lists cannot overlap
- All arrays must contain valid class IDs

## How Target Class Mapping Works

### 1. Grade Level Progression

The system automatically maps students from source classes to appropriate target classes based on grade levels:

```json
{
    "class_ids": [1, 2],      // Grade 9A, Grade 9B
    "target_class_ids": [3, 4] // Grade 10A, Grade 10B
}
```

Students from Grade 9 classes will be mapped to Grade 10 classes with the next sequential grade level.

### 2. Automatic Class Selection

For each student, the system:

1. Identifies their current class and grade level
2. Looks for a target class with `grade_level = current_grade + 1`
3. If exact match not found, selects the first available target class
4. Uses this target class for the promotion evaluation

### 3. Flexible Criteria Matching

The promotion logic tries to:

1. Find promotion criteria matching both source class AND target class
2. If not found, falls back to criteria matching just the source class
3. Uses the selected target class for the final promotion record

## Example Usage Scenarios

### Scenario 1: Standard Grade Progression

Promote all Grade 9 students to Grade 10:

```bash
curl -X POST "http://localhost:8080/api/promotions/bulk-evaluate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "academic_year_id": 1,
    "class_ids": [1, 2, 3],
    "target_class_ids": [4, 5, 6]
  }'
```

### Scenario 2: Selective Class Promotion

Promote students from specific classes to specific targets:

```json
{
    "academic_year_id": 1,
    "class_ids": [1],           // Only Grade 9A
    "target_class_ids": [4, 5]   // Can go to either Grade 10A or 10B
}
```

### Scenario 3: Multi-Grade Promotion

Handle multiple grade levels in one batch:

```json
{
    "academic_year_id": 1,
    "class_ids": [1, 2, 7, 8],      // Grade 9A, 9B, Grade 10A, 10B
    "target_class_ids": [4, 5, 9, 10] // Grade 10A, 10B, Grade 11A, 11B
}
```

## Response Format

### Success Response

```json
{
    "status": "success",
    "message": "Bulk evaluation started successfully",
    "data": {
        "id": 15,
        "school_id": 1,
        "academic_year_id": 1,
        "batch_name": "Bulk Evaluation - 2025-08-24 09:37",
        "status": "queued",
        "class_filters": [1, 2, 3],
        "target_class_ids": [4, 5, 6],
        "total_students": 0,
        "processed_students": 0,
        "promoted_students": 0
    }
}
```

### Validation Error Response

```json
{
    "status": "error",
    "message": "The given data was invalid.",
    "errors": {
        "target_class_ids": [
            "Target classes must have higher grade levels than source classes.",
            "Source and target classes cannot overlap."
        ]
    }
}
```

## Monitoring Progress

Use the batch progress API to monitor the evaluation:

### GET /api/promotions/batches/{id}/progress

```json
{
    "status": "success",
    "data": {
        "batch": {
            "id": 15,
            "status": "processing",
            "total_students": 150,
            "processed_students": 45,
            "promoted_students": 42,
            "failed_students": 3,
            "target_class_ids": [4, 5, 6]
        },
        "estimated_completion": "2025-08-24T10:15:00Z",
        "processing_rate": 2.5
    }
}
```

## Database Changes

### New Column in `promotion_batches` Table

```sql
ALTER TABLE promotion_batches 
ADD COLUMN target_class_ids JSON NULL AFTER class_filters;
```

### Updated Promotion Records

Student promotion records will now use the specified target classes instead of criteria defaults:

```sql
SELECT 
    sp.student_id,
    c1.name as from_class,
    c2.name as to_class,
    sp.promotion_status
FROM student_promotions sp
JOIN classes c1 ON sp.from_class_id = c1.id
JOIN classes c2 ON sp.to_class_id = c2.id
WHERE sp.academic_year_id = 1;
```

## Benefits

1. **Precise Control**: Schools can specify exact promotion paths
2. **Flexible Mapping**: Multiple target options for load balancing
3. **Grade Progression**: Automatic mapping based on grade levels  
4. **Validation**: Prevents invalid class combinations
5. **Audit Trail**: Target classes are stored in batch records

## Migration from Previous API

### Before (Without Target Classes)
```json
{
    "academic_year_id": 1,
    "class_ids": [1, 2, 3]
}
```

### After (With Target Classes) 
```json
{
    "academic_year_id": 1,
    "class_ids": [1, 2, 3],
    "target_class_ids": [4, 5, 6]
}
```

The `target_class_ids` parameter is now required for bulk evaluation to ensure explicit promotion paths.

## Queue Processing

The background job `ProcessBulkPromotionEvaluation` now handles target class mapping:

1. Receives target class IDs from the bulk request
2. For each student, determines appropriate target class
3. Evaluates against promotion criteria
4. Creates promotion records with correct target classes

This ensures accurate and controlled student promotions across the school system.
