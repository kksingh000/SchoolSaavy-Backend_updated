# Master Fee Components System

## Overview

The Master Fee Components system eliminates duplicate fee component names across schools by creating a centralized, reusable library of fee components. This system allows schools to use standardized fee components (like "Tuition Fee", "Transport Fee") while maintaining school-specific pricing and customization.

## Architecture

### 1. Master Fee Components (`master_fee_components`)
**Global, reusable component definitions that all schools can use**

```sql
CREATE TABLE master_fee_components (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) UNIQUE,           -- e.g., "Tuition Fee", "Transport Fee"
    description TEXT,                   -- Detailed description
    category VARCHAR(255),              -- academic, transport, library, sports, etc.
    is_required BOOLEAN DEFAULT TRUE,   -- Default requirement level
    default_frequency ENUM,             -- Monthly, Quarterly, Yearly
    is_active BOOLEAN DEFAULT TRUE,     -- Active/inactive status
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### 2. Fee Structure Components (`fee_structure_components`)
**School-specific implementations of master components with custom pricing**

```sql
ALTER TABLE fee_structure_components (
    id BIGINT PRIMARY KEY,
    fee_structure_id BIGINT,            -- Links to specific fee structure
    master_component_id BIGINT,         -- References master component
    component_name VARCHAR(255),        -- Legacy support for custom components
    custom_name VARCHAR(255),           -- School-specific custom name
    amount DECIMAL(10,2),               -- School-specific pricing
    frequency ENUM,                     -- School-specific frequency
    is_required BOOLEAN,                -- School-specific requirement
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Benefits

### ✅ **Eliminates Duplication**
- No more multiple "Tuition Fee" entries across different schools
- Standardized naming conventions across the platform
- Reduced database storage for component names

### ✅ **Maintains Flexibility**
- Schools can use standard components with custom pricing
- Option to add custom names for school-specific branding
- Legacy support for existing custom components

### ✅ **Improved Management**
- Centralized component library for easy maintenance
- Category-based organization (academic, transport, library, etc.)
- Easy addition of new standard components

### ✅ **Better Analytics**
- Platform-wide fee component usage statistics
- Standardized reporting across schools
- Easier benchmarking and analysis

## Master Component Categories

### 📚 **Academic Components** (Required)
- Tuition Fee
- Admission Fee
- Development Fee
- Examination Fee
- Registration Fee

### 🚌 **Transport Components** (Optional)
- Bus Fee
- Van Service Fee

### 📖 **Library Components** (Optional)
- Library Fee
- Book Rental Fee

### ⚽ **Sports & Events** (Optional)
- Sports Fee
- Annual Function Fee
- Excursion Fee

### 💻 **Technology Components** (Optional)
- Computer Lab Fee
- Internet Fee

### 🍽️ **Food & Catering** (Optional)
- Lunch Fee
- Snack Fee

### 🔒 **Security & Safety** (Optional)
- Security Fee
- Insurance Fee

### 🎒 **Services** (Optional)
- Uniform Fee
- Stationery Fee

### ⚠️ **Penalties** (Optional)
- Late Fee

## API Usage

### 1. Get All Master Components

```http
GET /api/fee-management/master-components
```

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
                "default_frequency": "Monthly"
            }
        ],
        "transport": [
            {
                "id": 6,
                "name": "Bus Fee",
                "description": "Monthly school bus transportation fee",
                "category": "transport",
                "is_required": false,
                "default_frequency": "Monthly"
            }
        ]
    }
}
```

### 2. Create Fee Structure with Master Components

```http
POST /api/fee-management/structures
```

**Request Body:**
```json
{
    "name": "Grade 5 Fee Structure",
    "class_id": 1,
    "academic_year_id": 1,
    "components": [
        {
            "master_component_id": 1,
            "amount": 5000,
            "frequency": "Monthly",
            "is_required": true
        },
        {
            "master_component_id": 6,
            "amount": 1200,
            "frequency": "Monthly",
            "is_required": false,
            "custom_name": "School Bus Service"
        }
    ]
}
```

### 3. Legacy Support for Custom Components

```json
{
    "components": [
        {
            "name": "Custom Special Fee",
            "amount": 500,
            "frequency": "Yearly",
            "is_required": false
        }
    ]
}
```

## Implementation Details

### Model Relationships

```php
// MasterFeeComponent Model
class MasterFeeComponent extends Model
{
    public function feeStructureComponents()
    {
        return $this->hasMany(FeeStructureComponent::class, 'master_component_id');
    }
}

// FeeStructureComponent Model
class FeeStructureComponent extends Model
{
    public function masterComponent()
    {
        return $this->belongsTo(MasterFeeComponent::class, 'master_component_id');
    }
    
    // Smart name resolution
    public function getNameAttribute()
    {
        if ($this->custom_name) {
            return $this->custom_name;
        }
        
        if ($this->masterComponent) {
            return $this->masterComponent->name;
        }
        
        return $this->component_name; // Fallback for legacy
    }
}
```

### Service Layer Logic

```php
// Creating components with master component reference
foreach ($data['components'] as $component) {
    $componentData = [
        'fee_structure_id' => $feeStructure->id,
        'amount' => $component['amount'],
        'frequency' => $component['frequency'],
    ];

    if (isset($component['master_component_id'])) {
        $componentData['master_component_id'] = $component['master_component_id'];
        $componentData['custom_name'] = $component['custom_name'] ?? null;
    } else {
        // Legacy support
        $componentData['component_name'] = $component['name'];
    }

    FeeStructureComponent::create($componentData);
}
```

## Migration Strategy

### Phase 1: Add Master Components (✅ Completed)
- Create `master_fee_components` table
- Modify `fee_structure_components` table
- Add relationships and foreign keys

### Phase 2: Data Migration (Future)
```php
// Migrate existing component names to master components
$existingComponents = FeeStructureComponent::whereNull('master_component_id')
    ->groupBy('component_name')
    ->get();

foreach ($existingComponents as $component) {
    $masterComponent = MasterFeeComponent::firstOrCreate([
        'name' => $component->component_name,
        'category' => 'academic', // Default category
        'is_required' => true
    ]);
    
    // Update all components with this name
    FeeStructureComponent::where('component_name', $component->component_name)
        ->update(['master_component_id' => $masterComponent->id]);
}
```

### Phase 3: Cleanup (Future)
- Remove legacy `component_name` column after full migration
- Add unique constraints
- Optimize indexes

## Backward Compatibility

- ✅ **Existing APIs** continue to work without changes
- ✅ **Legacy component_name** field still supported
- ✅ **Gradual migration** path available
- ✅ **No data loss** during transition

## Performance Considerations

### Caching Strategy
```php
// Master components cached for 1 hour (rarely change)
Cache::remember('master_fee_components', 3600, function () {
    return MasterFeeComponent::active()->get()->groupBy('category');
});

// School-specific components cached for 5 minutes
Cache::remember("fee_structure_{$schoolId}_{$structureId}", 300, function () {
    return FeeStructure::with('components.masterComponent')->find($structureId);
});
```

### Database Optimization
- Indexed foreign keys for fast lookups
- Category-based indexing for filtering
- Proper relationship eager loading

## Future Enhancements

### 1. **Component Templates**
- Pre-configured component sets for different school types
- Quick setup for new schools

### 2. **Component Dependencies**
- Define relationships between components
- Automatic inclusion of dependent components

### 3. **Dynamic Pricing**
- Percentage-based pricing from base amounts
- Regional pricing adjustments

### 4. **Component Analytics**
- Usage statistics across schools
- Popular component combinations
- Pricing benchmarks

## Testing

```php
// Test master component creation
$masterComponent = MasterFeeComponent::create([
    'name' => 'Test Fee',
    'category' => 'academic',
    'is_required' => true
]);

// Test fee structure with master component
$feeStructure = FeeStructure::create([...]);
$component = FeeStructureComponent::create([
    'fee_structure_id' => $feeStructure->id,
    'master_component_id' => $masterComponent->id,
    'amount' => 1000
]);

// Verify name resolution
$this->assertEquals('Test Fee', $component->name);
```

This master components system provides a robust foundation for scalable fee management while maintaining full backward compatibility and flexibility for schools' specific needs.
