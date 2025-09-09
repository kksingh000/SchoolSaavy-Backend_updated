# Updated Fee Management API Documentation

## Master Component Integration

The Fee Management APIs have been updated to use Master Component IDs instead of individual component details. This standardizes fee components across all schools while maintaining flexibility.

## API Changes

### 1. Create Fee Structure

**Endpoint:** `POST /api/fee-management/structures`

**New Request Format:**
```json
{
    "name": "Grade 5 Fee Structure",
    "class_id": 1,
    "academic_year_id": 1,
    "description": "Fee structure for Grade 5 students",
    "components": [
        {
            "master_component_id": 1,          // ID from master_fee_components table
            "amount": 5000,
            "frequency": "Monthly",
            "is_required": true,
            "custom_name": null                // Optional: School-specific name override
        },
        {
            "master_component_id": 6,          // Bus Fee from master components
            "amount": 1200,
            "frequency": "Monthly",
            "is_required": false,
            "custom_name": "School Transport Service"  // Custom branding
        },
        {
            "master_component_id": 8,          // Library Fee
            "amount": 500,
            "frequency": "Yearly",
            "is_required": false
        }
    ]
}
```

**Old Format (Deprecated but still supported):**
```json
{
    "components": [
        {
            "name": "Tuition Fee",            // Direct name (legacy)
            "amount": 5000,
            "frequency": "Monthly"
        }
    ]
}
```

### 2. Update Fee Structure

**Endpoint:** `PUT /api/fee-management/structures/{id}`

**Request Format:** Same as create, but all fields are optional for updates.

### 3. Get Master Components

**Endpoint:** `GET /api/fee-management/master-components`

**Query Parameters:**
- `category` - Filter by category (academic, transport, library, etc.)
- `is_required` - Filter by requirement (true/false)
- `search` - Search by component name

**Response:**
```json
{
    "status": "success",
    "data": {
        "academic": [
            {
                "id": 1,
                "name": "Tuition Fee",
                "description": "Monthly tuition fee for academic instruction",
                "category": "academic",
                "is_required": true,
                "default_frequency": "Monthly",
                "display_name": "Tuition Fee (Academic)"
            },
            {
                "id": 2,
                "name": "Admission Fee",
                "description": "One-time admission fee for new students",
                "category": "academic",
                "is_required": true,
                "default_frequency": "Yearly",
                "display_name": "Admission Fee (Academic)"
            }
        ],
        "transport": [
            {
                "id": 6,
                "name": "Bus Fee",
                "description": "Monthly school bus transportation fee",
                "category": "transport",
                "is_required": false,
                "default_frequency": "Monthly",
                "display_name": "Bus Fee (Transport)"
            }
        ]
    }
}
```

### 4. Get Specific Master Component

**Endpoint:** `GET /api/fee-management/master-components/{id}`

**Response:**
```json
{
    "status": "success",
    "data": {
        "id": 1,
        "name": "Tuition Fee",
        "description": "Monthly tuition fee for academic instruction",
        "category": "academic",
        "is_required": true,
        "default_frequency": "Monthly",
        "is_active": true,
        "created_at": "2025-09-09T00:00:00.000000Z",
        "updated_at": "2025-09-09T00:00:00.000000Z"
    }
}
```

## Validation Rules

### Fee Structure Components

| Field | Type | Required | Validation Rules |
|-------|------|----------|------------------|
| `master_component_id` | integer | Yes | Must exist in master_fee_components table and be active |
| `custom_name` | string | No | Max 255 characters |
| `amount` | decimal | Yes | Min: 0, Max: 999999.99 |
| `frequency` | string | Yes | One of: Monthly, Quarterly, Yearly |
| `is_required` | boolean | No | Default from master component |

### Additional Validation
- Master component must be active (`is_active = true`)
- No duplicate master components in the same fee structure
- School cannot modify master component data, only reference it

## Benefits of Master Component Integration

### ✅ **Standardization**
```json
// Before: Inconsistent naming
School A: {"name": "Tuition Fee"}
School B: {"name": "Monthly Fee"}  
School C: {"name": "Tuition"}

// After: Consistent referencing
All Schools: {"master_component_id": 1}  // "Tuition Fee"
```

### ✅ **Reduced API Payload**
```json
// Before: Repetitive data
{
    "components": [
        {
            "name": "Tuition Fee",
            "description": "Monthly tuition fee",
            "category": "academic",
            "amount": 5000,
            "frequency": "Monthly"
        }
    ]
}

// After: Clean reference
{
    "components": [
        {
            "master_component_id": 1,
            "amount": 5000,
            "frequency": "Monthly"
        }
    ]
}
```

### ✅ **Flexible Customization**
```json
{
    "master_component_id": 6,           // Standard "Bus Fee"
    "custom_name": "Premium Transport", // School-specific branding
    "amount": 1500,                     // School-specific pricing
    "frequency": "Monthly"              // School-specific frequency
}
```

## Frontend Integration Guide

### 1. Load Master Components
```javascript
// Get all master components grouped by category
const response = await fetch('/api/fee-management/master-components');
const { data: masterComponents } = await response.json();

// Build component selector UI
Object.entries(masterComponents).forEach(([category, components]) => {
    console.log(`${category}:`, components);
});
```

### 2. Create Fee Structure Form
```javascript
const feeStructureData = {
    name: "Grade 5 Fees",
    class_id: 1,
    academic_year_id: 1,
    components: selectedComponents.map(comp => ({
        master_component_id: comp.masterId,
        amount: comp.schoolAmount,
        frequency: comp.schoolFrequency,
        custom_name: comp.customName || null
    }))
};

await fetch('/api/fee-management/structures', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(feeStructureData)
});
```

### 3. Display Components with Resolved Names
```javascript
// Component will automatically resolve name:
// - Uses custom_name if provided
// - Falls back to master component name
// - Falls back to legacy component_name

const displayName = component.custom_name || 
                   component.master_component?.name || 
                   component.component_name;
```

## Migration Strategy

### Phase 1: Dual Support (Current)
- ✅ APIs accept both master_component_id and legacy name
- ✅ Validation supports both formats
- ✅ Backward compatibility maintained

### Phase 2: Master Component Preferred
- Update frontend to use master components
- Deprecation warnings for legacy format
- Analytics on usage patterns

### Phase 3: Master Component Only
- Remove legacy name support
- Cleanup deprecated validation rules
- Full standardization achieved

## Error Handling

### Common Validation Errors
```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "components.0.master_component_id": [
            "Selected master component is not active or does not exist"
        ],
        "components.1.amount": [
            "Fee component amount is required"
        ]
    }
}
```

### Success Response
```json
{
    "status": "success",
    "message": "Fee structure created successfully",
    "data": {
        "id": 1,
        "name": "Grade 5 Fee Structure",
        "components": [
            {
                "id": 1,
                "master_component_id": 1,
                "name": "Tuition Fee",        // Resolved name
                "custom_name": null,
                "amount": "5000.00",
                "frequency": "Monthly",
                "master_component": {
                    "id": 1,
                    "name": "Tuition Fee",
                    "category": "academic"
                }
            }
        ]
    }
}
```

This updated API structure provides better standardization while maintaining the flexibility schools need for their specific requirements.
