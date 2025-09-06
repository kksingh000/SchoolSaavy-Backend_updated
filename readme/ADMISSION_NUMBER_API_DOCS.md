# Admission Number Generation API Documentation

## Overview
This API provides comprehensive admission number generation functionality for schools with configurable settings and incremental numbering.

## Endpoints

### 1. Generate Single Admission Number
**GET** `/api/admission-number/generate`

Generates the next available admission number based on school settings.

**Response:**
```json
{
    "status": "success",
    "message": "Admission number generated successfully",
    "data": {
        "admission_number": "STU20250354",
        "settings": {
            "prefix": "STU",
            "format": "sequential",
            "include_year": true,
            "year_format": "YYYY",
            "padding_length": 4
        },
        "preview": [
            "STU20250354",
            "STU20250355",
            "STU20250356"
        ]
    }
}
```

### 2. Generate Batch Admission Numbers
**POST** `/api/admission-number/generate-batch`

Generates multiple admission numbers at once (useful for bulk imports).

**Request Body:**
```json
{
    "count": 5
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Batch admission numbers generated successfully",
    "data": {
        "admission_numbers": [
            "STU20250354",
            "STU20250355",
            "STU20250356",
            "STU20250357",
            "STU20250358"
        ],
        "count": 5,
        "settings": {
            "prefix": "STU",
            "format": "sequential",
            "include_year": true,
            "year_format": "YYYY",
            "padding_length": 4
        }
    }
}
```

### 3. Check Admission Number Availability
**GET** `/api/admission-number/check-availability?admission_number=STU20250001`

**Response:**
```json
{
    "status": "success",
    "data": {
        "admission_number": "STU20250001",
        "available": true,
        "message": "Admission number is available"
    }
}
```

### 4. Get Current Settings
**GET** `/api/admission-number/settings`

**Response:**
```json
{
    "status": "success",
    "message": "Admission number settings retrieved successfully",
    "data": {
        "prefix": "STU",
        "format": "sequential",
        "start_from": 1,
        "include_year": true,
        "year_format": "YYYY",
        "padding_length": 4,
        "current_number": "STU20250353"
    }
}
```

### 5. Update Settings
**PUT** `/api/admission-number/settings`

**Request Body:**
```json
{
    "prefix": "SCHOOL",
    "format": "year_sequential",
    "start_from": 1,
    "include_year": true,
    "year_format": "YY",
    "padding_length": 5
}
```

**Response:**
```json
{
    "status": "success",
    "message": "Admission number settings updated successfully",
    "data": {
        "prefix": "SCHOOL",
        "format": "year_sequential",
        "start_from": 1,
        "include_year": true,
        "year_format": "YY",
        "padding_length": 5
    }
}
```

## Settings Configuration

### Available Options:

1. **prefix**: String prefix for admission numbers (e.g., "STU", "SCHOOL")
2. **format**: 
   - `sequential`: Continuous numbering across years
   - `year_sequential`: Reset numbering each year
3. **start_from**: Starting number for the sequence
4. **include_year**: Whether to include year in the admission number
5. **year_format**: 
   - `YYYY`: Full year (2025)
   - `YY`: Short year (25)
6. **padding_length**: Number of digits for the sequence number (with zero padding)

### Examples of Generated Numbers:

| Settings | Generated Number |
|----------|-----------------|
| prefix: "STU", year: true, format: YYYY, padding: 4 | STU20250001 |
| prefix: "SCHOOL", year: true, format: YY, padding: 3 | SCHOOL25001 |
| prefix: "", year: false, padding: 6 | 000001 |
| prefix: "ADM", year: true, format: YYYY, padding: 5 | ADM202500001 |

## Features

1. **Automatic Increment**: Numbers are automatically incremented based on existing students
2. **Uniqueness Guarantee**: System ensures no duplicate admission numbers
3. **Year-based Reset**: Option to reset numbering each academic year
4. **Flexible Format**: Customizable prefix, year inclusion, and padding
5. **Batch Generation**: Generate multiple numbers at once for bulk operations
6. **Availability Check**: Verify if a specific admission number is available
7. **School Isolation**: All numbers are school-specific (multi-tenant safe)

## Integration with Student APIs

The admission number generation integrates seamlessly with:
- Student creation API (auto-generates if not provided)
- Bulk student import system
- Student update API (validates uniqueness)

## Error Handling

The API includes comprehensive error handling for:
- Invalid settings values
- Duplicate admission numbers
- School validation
- Maximum generation attempts
- Batch generation limits (max 100 per request)
