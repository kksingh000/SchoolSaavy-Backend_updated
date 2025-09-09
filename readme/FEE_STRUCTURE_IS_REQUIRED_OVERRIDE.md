# Fee Structure - required Field Override

## Overview
The fee structure API now supports overriding the `required` field for each component. This allows schools to customize whether a fee component is mandatory or optional, regardless of the master component's default setting.

## API Usage

### Creating Fee Structure

When creating a fee structure, the `required` field is **required** for each component:

```json
POST /api/fee-structures

{
  "name": "Grade 5A Fee Structure",
  "class_id": 1,
  "academic_year_id": 1,
  "description": "Fee structure for Grade 5A students",
  "components": [
    {
      "master_component_id": 1,
      "custom_name": null,
      "amount": 5000.00,
      "frequency": "Monthly",
      "required": true  // REQUIRED: Override master component setting
    },
    {
      "master_component_id": 2,
      "custom_name": null,
      "amount": 15000.00,
      "frequency": "One-Time",
      "required": true  // REQUIRED: One-time admission fee
    },
    {
      "master_component_id": 5,
      "custom_name": "School Bus Service",
      "amount": 2000.00,
      "frequency": "Monthly",
      "required": false  // REQUIRED: Make transport optional
    },
    {
      "master_component_id": 8,
      "custom_name": null,
      "amount": 1500.00,
      "frequency": "Yearly",
      "required": true  // REQUIRED: Make books mandatory
    }
  ]
}
```

### Updating Fee Structure

When updating a fee structure, if components are provided, `required` is required for each component:

```json
PUT /api/fee-structures/1

{
  "name": "Updated Grade 5A Fee Structure",
  "components": [
    {
      "master_component_id": 1,
      "amount": 5500.00,
      "frequency": "Monthly",
      "required": false  // REQUIRED: Change tuition to optional
    }
  ]
}
```

## Behavior

### Default Logic
1. **API Value Provided**: Uses the `required` value from the API request
2. **API Value Not Provided**: Falls back to master component's `is_required` value
3. **Master Component Not Found**: Defaults to `true` (required)

### Legacy Components
For legacy components (without master_component_id), the old behavior is maintained:
- Uses API value if provided
- Defaults to `true` if not provided

## Validation Rules

### Component Level Validation
- `components.*.required` is **required** and must be boolean (`true` or `false`)
- Validation error: "Component requirement status is required"
- Type error: "Component requirement status must be true or false"

### Example Validation Errors

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "components.0.required": [
      "Component requirement status is required"
    ],
    "components.1.required": [
      "Component requirement status must be true or false"
    ]
  }
}
```

## Impact on Student Fee Plans

### Automatic Plan Creation
When a fee structure is created, the system automatically creates default student fee plans containing only **required components** (`required: true`).

### Manual Plan Creation
When manually creating student fee plans, schools can:
1. Include all required components (mandatory)
2. Optionally add non-required components based on student enrollment

## Use Cases

### Mandatory vs Optional Services
```json
{
  "components": [
    {
      "master_component_id": 1,  // Tuition Fee
      "amount": 5000.00,
      "frequency": "Monthly",
      "required": true        // Always mandatory
    },
    {
      "master_component_id": 5,  // Transport Fee
      "amount": 2000.00,
      "frequency": "Monthly", 
      "required": false       // Optional service
    },
    {
      "master_component_id": 12, // Meal Plan
      "amount": 1000.00,
      "frequency": "Monthly",
      "required": false       // Optional service
    }
  ]
}
```

### Grade-Specific Requirements
```json
{
  "components": [
    {
      "master_component_id": 8,  // Laboratory Fee
      "amount": 1500.00,
      "frequency": "Yearly",
      "required": true        // Required for science grades
    },
    {
      "master_component_id": 15, // Sports Equipment
      "amount": 800.00,
      "frequency": "Yearly",
      "required": false       // Optional for younger grades
    }
  ]
}
```

## Migration Notes

### Existing Data
- Existing fee structures will continue to work
- Components without explicit `is_required` values will use master component defaults
- No database migration required

### API Changes
- **Breaking Change**: `required` is now required when creating/updating fee structure components
- Frontend applications must be updated to include this field
- API documentation should be updated to reflect the required field

## Testing

### Test Scenarios
1. Create fee structure with explicit `required` values
2. Create fee structure mixing required and optional components
3. Update fee structure changing `required` status
4. Verify automatic student plan creation uses only required components
5. Test validation errors for missing `required` field
