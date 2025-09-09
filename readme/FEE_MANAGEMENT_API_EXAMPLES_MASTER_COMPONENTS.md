# Fee Management API Examples - Master Components

## Before vs After Comparison

### 1. Get Master Components First

```http
GET /api/fee-management/master-components?category=academic
Authorization: Bearer {token}
Content-Type: application/json
```

**Response:**
```json
{
    "status": "success", 
    "data": {
        "academic": [
            {"id": 1, "name": "Tuition Fee", "category": "academic", "is_required": true},
            {"id": 2, "name": "Admission Fee", "category": "academic", "is_required": true},
            {"id": 3, "name": "Development Fee", "category": "academic", "is_required": true}
        ]
    }
}
```

### 2. Create Fee Structure - OLD WAY ❌

```http
POST /api/fee-management/structures
```

```json
{
    "name": "Grade 5 Fee Structure",
    "class_id": 1,
    "academic_year_id": 1,
    "components": [
        {
            "name": "Tuition Fee",          // ❌ Hardcoded string
            "amount": 5000,
            "frequency": "Monthly"
        },
        {
            "name": "Transport Fee",        // ❌ Inconsistent naming
            "amount": 1200, 
            "frequency": "Monthly"
        },
        {
            "name": "Library Fee",          // ❌ Duplicate across schools
            "amount": 500,
            "frequency": "Yearly"
        }
    ]
}
```

### 3. Create Fee Structure - NEW WAY ✅

```http
POST /api/fee-management/structures
```

```json
{
    "name": "Grade 5 Fee Structure",
    "class_id": 1,
    "academic_year_id": 1,
    "components": [
        {
            "master_component_id": 1,       // ✅ Reference to "Tuition Fee"
            "amount": 5000,
            "frequency": "Monthly",
            "is_required": true
        },
        {
            "master_component_id": 6,       // ✅ Reference to "Bus Fee"
            "amount": 1200,
            "frequency": "Monthly", 
            "is_required": false,
            "custom_name": "School Transport"  // ✅ Optional custom branding
        },
        {
            "master_component_id": 8,       // ✅ Reference to "Library Fee"
            "amount": 500,
            "frequency": "Yearly",
            "is_required": false
        }
    ]
}
```

### 4. Response - NEW FORMAT ✅

```json
{
    "status": "success",
    "message": "Fee structure created successfully",
    "data": {
        "id": 1,
        "name": "Grade 5 Fee Structure",
        "school_id": 1,
        "class_id": 1,
        "academic_year_id": 1,
        "components": [
            {
                "id": 1,
                "fee_structure_id": 1,
                "master_component_id": 1,
                "component_name": null,
                "custom_name": null,
                "name": "Tuition Fee",           // ✅ Resolved from master
                "category": "academic",          // ✅ From master component
                "amount": "5000.00",
                "frequency": "Monthly",
                "is_required": true,
                "master_component": {
                    "id": 1,
                    "name": "Tuition Fee",
                    "description": "Monthly tuition fee for academic instruction",
                    "category": "academic",
                    "is_required": true,
                    "default_frequency": "Monthly"
                }
            },
            {
                "id": 2,
                "fee_structure_id": 1,
                "master_component_id": 6,
                "component_name": null,
                "custom_name": "School Transport",
                "name": "School Transport",      // ✅ Uses custom_name
                "category": "transport",         // ✅ From master component
                "amount": "1200.00",
                "frequency": "Monthly",
                "is_required": false,
                "master_component": {
                    "id": 6,
                    "name": "Bus Fee",
                    "description": "Monthly school bus transportation fee",
                    "category": "transport",
                    "is_required": false,
                    "default_frequency": "Monthly"
                }
            }
        ]
    }
}
```

## Frontend Implementation Examples

### React Component - Master Component Selector

```jsx
import React, { useState, useEffect } from 'react';

const FeeStructureForm = () => {
    const [masterComponents, setMasterComponents] = useState({});
    const [selectedComponents, setSelectedComponents] = useState([]);

    useEffect(() => {
        // Load master components
        fetch('/api/fee-management/master-components')
            .then(res => res.json())
            .then(data => setMasterComponents(data.data));
    }, []);

    const addComponent = (masterComponent) => {
        setSelectedComponents([...selectedComponents, {
            master_component_id: masterComponent.id,
            name: masterComponent.name,
            category: masterComponent.category,
            amount: '',
            frequency: masterComponent.default_frequency,
            is_required: masterComponent.is_required,
            custom_name: ''
        }]);
    };

    return (
        <div>
            <h3>Available Components</h3>
            {Object.entries(masterComponents).map(([category, components]) => (
                <div key={category}>
                    <h4>{category.charAt(0).toUpperCase() + category.slice(1)}</h4>
                    {components.map(component => (
                        <button 
                            key={component.id}
                            onClick={() => addComponent(component)}
                            className={component.is_required ? 'required' : 'optional'}
                        >
                            {component.name} 
                            {component.is_required ? ' (Required)' : ' (Optional)'}
                        </button>
                    ))}
                </div>
            ))}

            <h3>Selected Components</h3>
            {selectedComponents.map((component, index) => (
                <div key={index} className="component-form">
                    <h5>{component.name}</h5>
                    <input
                        type="text"
                        placeholder="Custom Name (optional)"
                        value={component.custom_name}
                        onChange={(e) => {
                            const updated = [...selectedComponents];
                            updated[index].custom_name = e.target.value;
                            setSelectedComponents(updated);
                        }}
                    />
                    <input
                        type="number"
                        placeholder="Amount"
                        value={component.amount}
                        onChange={(e) => {
                            const updated = [...selectedComponents];
                            updated[index].amount = e.target.value;
                            setSelectedComponents(updated);
                        }}
                    />
                    <select
                        value={component.frequency}
                        onChange={(e) => {
                            const updated = [...selectedComponents];
                            updated[index].frequency = e.target.value;
                            setSelectedComponents(updated);
                        }}
                    >
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Yearly">Yearly</option>
                    </select>
                </div>
            ))}
        </div>
    );
};
```

### JavaScript - API Call

```javascript
const createFeeStructure = async (formData) => {
    const payload = {
        name: formData.name,
        class_id: formData.classId,
        academic_year_id: formData.academicYearId,
        components: selectedComponents.map(comp => ({
            master_component_id: comp.master_component_id,
            amount: parseFloat(comp.amount),
            frequency: comp.frequency,
            is_required: comp.is_required,
            custom_name: comp.custom_name || null
        }))
    };

    try {
        const response = await fetch('/api/fee-management/structures', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            console.log('Fee structure created:', result.data);
            // Handle success - redirect or show message
        } else {
            console.error('Validation errors:', result.errors);
            // Handle validation errors
        }
    } catch (error) {
        console.error('API Error:', error);
    }
};
```

## Benefits Summary

### 🎯 **Standardization**
- All schools use same component names
- Consistent data across platform
- Easy analytics and reporting

### 🚀 **Performance** 
- Smaller API payloads
- Reduced database storage
- Faster response times

### 🔧 **Flexibility**
- Custom naming per school
- School-specific pricing
- Optional/required overrides

### 📊 **Analytics**
- Platform-wide component usage
- Pricing benchmarks
- Popular combinations

### 🛠️ **Maintenance**
- Centralized component management
- Easy addition of new components
- Consistent validation rules

This new approach provides the perfect balance between standardization and flexibility!
