# Student Fee API Endpoints Documentation

## Overview

This document describes the API endpoints for retrieving student fee information in the SchoolSavvy system. There are two main endpoints:

1. **Student Fee Summary** - A lightweight endpoint providing a tabular view of student fee status
2. **Detailed Student Fee Information** - A comprehensive endpoint with complete fee details for a specific student

## Endpoints

### 1. Student Fee Summary

```
GET /api/fee-management/payments/student-fee-details
```

This endpoint returns a streamlined view of student fee information, optimized for tabular display and faster loading times.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| class_id | integer | No | Filter results by class ID |
| student_id | integer | No | Filter results by student ID |
| academic_year_id | integer | No | Filter results by academic year ID (defaults to current academic year) |
| per_page | integer | No | Number of results per page (default: 15) |

#### Response Format

```json
{
  "status": "success",
  "message": "Student fee details retrieved successfully",
  "data": [
    {
      "student_id": 1,
      "student_name": "John Doe",
      "class_id": 5,
      "class": "Grade 5A",
      "academic_year": "2024-25",
      "academic_year_id": 3,
      "total_fee": 12100,
      "total_paid": 5000,
      "total_due": 2200,
      "total_overdue": 4900,
      "is_paid_up_to_date": false
    },
    {
      "student_id": 2,
      "student_name": "Jane Smith",
      "class_id": 5,
      "class": "Grade 5A",
      "academic_year": "2024-25",
      "academic_year_id": 3,
      "total_fee": 12100,
      "total_paid": 12100,
      "total_due": 0,
      "total_overdue": 0,
      "is_paid_up_to_date": true
    }
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "/api/fee-management/payments/student-fee-details",
    "per_page": 15,
    "to": 2,
    "total": 2
  },
  "links": {
    "first": "http://example.com/api/fee-management/payments/student-fee-details?page=1",
    "last": "http://example.com/api/fee-management/payments/student-fee-details?page=1",
    "prev": null,
    "next": null
  }
}
```

### 2. Detailed Student Fee Information

```
GET /api/fee-management/payments/{studentId}/student-fee-details
```

This endpoint returns comprehensive fee details for a specific student, including all fee components and installments.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| studentId | integer | Yes | The ID of the student (in URL path) |
| academic_year_id | integer | No | Filter by academic year ID (defaults to current academic year) |

#### Response Format

```json
{
  "status": "success",
  "message": "Detailed student fee information retrieved successfully",
  "data": {
    "student_id": 1,
    "student_name": "John Doe",
    "class": "Grade 5A",
    "academic_year": "2024-25",
    "fee_plan": {
      "total_fee": 12100,
      "total_paid": 5000,
      "total_due": 2200,
      "total_overdue": 4900,
      "is_paid_up_to_date": false,
      "paid_up_to_month": "June 2025",
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
              "paid_amount": 1000,
              "status": "Paid"
            },
            {
              "installment_no": 2,
              "due_date": "2025-04-30",
              "amount": 1000,
              "paid_amount": 1000,
              "status": "Paid"
            },
            {
              "installment_no": 3,
              "due_date": "2025-05-31",
              "amount": 1000,
              "paid_amount": 1000,
              "status": "Paid"
            },
            {
              "installment_no": 4,
              "due_date": "2025-06-30",
              "amount": 1000,
              "paid_amount": 1000,
              "status": "Paid"
            },
            {
              "installment_no": 5,
              "due_date": "2025-07-31",
              "amount": 1000,
              "paid_amount": 500,
              "status": "Overdue"
            },
            {
              "installment_no": 6,
              "due_date": "2025-08-31",
              "amount": 1000,
              "paid_amount": 0,
              "status": "Overdue"
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
              "paid_amount": 100,
              "status": "Paid"
            }
          ]
        }
      ]
    }
  }
}
```

## Query Optimization

The student fee summary endpoint has been optimized for performance:

1. **Selective Loading**: Only loads the specific fields needed for the summary view
2. **Reduced Data Transfer**: Returns a compact dataset suitable for tabular display
3. **Efficient Caching**: Uses Redis caching with specific cache keys to improve response times
4. **Targeted Queries**: Uses precise database queries with efficient indexing

## Usage Examples

### Getting summary fee information for all students in a class

```
GET /api/fee-management/payments/student-fee-details?class_id=5
```

### Getting summary fee information for a specific student

```
GET /api/fee-management/payments/student-fee-details?student_id=123
```

### Getting detailed fee information for a specific student

```
GET /api/fee-management/payments/123/student-fee-details
```

### Getting detailed fee information for a specific academic year

```
GET /api/fee-management/payments/123/student-fee-details?academic_year_id=3
```

## Error Handling

Both endpoints use consistent error handling:

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

### Not Found Error (Detailed endpoint only)
```json
{
  "status": "error",
  "message": "No fee plan found for this student"
}
```

### Server Error
```json
{
  "status": "error",
  "message": "Failed to retrieve student fee details: [error message]"
}
```
