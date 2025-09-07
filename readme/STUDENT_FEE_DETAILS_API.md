# Student Fee Details API Documentation

## Overview

The Student Fee Details API provides a student-centric view of fee information, organizing data by student rather than by fee components. This makes it easier to understand a student's complete fee status at a glance.

## Endpoint

```
GET /api/fee-management/payments/student-fee-details
```

## Authentication

This endpoint requires authentication and the 'fee-management' module to be active.

## Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| class_id | integer | No | Filter results by class ID |
| student_id | integer | No | Filter results by student ID |
| academic_year_id | integer | No | Filter results by academic year ID (defaults to current academic year) |
| per_page | integer | No | Number of results per page (default: 15) |

## Response Format

```json
{
  "status": "success",
  "message": "Student fee details retrieved successfully",
  "data": [
    {
      "student_id": 1,
      "student_name": "John Doe",
      "class": "Grade 5A",
      "academic_year": "2024-25",
      "fee_plan": {
        "total_fee": 12100,
        "total_paid": 0,
        "total_due": 2200,
        "total_overdue": 100,
        "is_paid_up_to_date": false,
        "paid_up_to_month": "August 2025",
        "components": [
          {
            "name": "Tuition",
            "annual_amount": 12000,
            "frequency": "Monthly",
            "installments": [
              {
                "installment_no": 1,
                "due_date": "2025-03-31",
                "amount": 1000,
                "paid_amount": 0,
                "status": "Pending"
              },
              {
                "installment_no": 2,
                "due_date": "2025-04-30",
                "amount": 1000,
                "paid_amount": 0,
                "status": "Pending"
              }
            ]
          },
          {
            "name": "Misc",
            "annual_amount": 100,
            "frequency": "Yearly",
            "installments": [
              {
                "installment_no": 1,
                "due_date": "2025-03-31",
                "amount": 100,
                "paid_amount": 0,
                "status": "Overdue"
              }
            ]
          }
        ]
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "/api/fee-management/payments/student-fee-details",
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

## Response Fields

### Root Level
- **status**: "success" or "error"
- **message**: Response message
- **data**: Array of student fee details
- **meta**: Pagination metadata

### Student Fee Details
- **student_id**: Student's unique identifier
- **student_name**: Student's full name
- **class**: Student's current class
- **academic_year**: Academic year for this fee plan
- **fee_plan**: Contains all fee plan information

### Fee Plan
- **total_fee**: Total fee amount for the student
- **total_paid**: Total amount paid so far
- **total_due**: Total amount due (excluding overdue)
- **total_overdue**: Total overdue amount
- **is_paid_up_to_date**: Boolean indicating if all due installments are paid
- **paid_up_to_month**: Month and year up to which fees are paid
- **components**: Array of fee components

### Component
- **name**: Name of the fee component
- **annual_amount**: Total annual amount for this component
- **frequency**: Payment frequency (Monthly, Quarterly, Yearly, etc.)
- **installments**: Array of installments for this component

### Installment
- **installment_no**: Installment number
- **due_date**: Date when payment is due
- **amount**: Amount to be paid
- **paid_amount**: Amount already paid
- **status**: Current status (Paid, Pending, Overdue)

## Error Responses

### Authentication Error
```json
{
  "status": "error",
  "message": "Unauthenticated"
}
```

### Module Access Error
```json
{
  "status": "error",
  "message": "Access to fee-management module is denied"
}
```

### Server Error
```json
{
  "status": "error",
  "message": "Failed to retrieve student fee details: [error message]"
}
```

## Usage Examples

### Get all students' fee details
```
GET /api/fee-management/payments/student-fee-details
```

### Get fee details for a specific student
```
GET /api/fee-management/payments/student-fee-details?student_id=123
```

### Get fee details for a specific class in a specific academic year
```
GET /api/fee-management/payments/student-fee-details?class_id=45&academic_year_id=12
```
