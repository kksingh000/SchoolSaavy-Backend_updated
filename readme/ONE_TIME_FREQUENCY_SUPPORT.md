# One-Time Frequency Support for Fee Components

## Overview
Added support for 'One-Time' frequency option in fee components to handle fees that are charged only once, such as admission fees, registration fees, and security deposits.

## Changes Made

### 1. Database Schema Updates

#### Fee Structure Components Table
- **Migration**: `2025_09_09_220706_add_one_time_frequency_to_fee_structure_components_table.php`
- **Change**: Updated frequency enum to include 'One-Time'
- **Values**: `['Monthly', 'Quarterly', 'Yearly', 'One-Time']`

#### Master Fee Components Table
- **Migration**: `2025_09_09_220754_add_one_time_frequency_to_master_fee_components_table.php`
- **Change**: Updated default_frequency enum to include 'One-Time'
- **Values**: `['Monthly', 'Quarterly', 'Yearly', 'One-Time']`

### 2. API Validation Updates

#### FeeStructureRequest.php
- **Updated frequency validation rule** to accept 'One-Time'
- **Updated validation message** to include 'One-Time' in allowed values

```php
'components.*.frequency' => ['required', 'string', Rule::in(['Monthly', 'Quarterly', 'Yearly', 'One-Time'])],
```

**Validation Message**:
```
'Fee component frequency must be one of: Monthly, Quarterly, Yearly, One-Time'
```

### 3. Job Processing Updates

#### GenerateStudentFeeInstallments.php
- **Added support for 'One-Time' frequency** in installment generation
- **New method**: `generateOneTimeInstallment()`
- **Behavior**: Creates a single installment due immediately (same as yearly)

```php
case 'One-Time':
    $this->generateOneTimeInstallment($schoolId, $component, $startDate, $amount);
    break;
```

### 4. Master Fee Components Seeder Updates

#### Updated Components to Use One-Time Frequency
- **Admission Fee**: Changed from 'Yearly' to 'One-Time'
- **Registration Fee**: Changed from 'Yearly' to 'One-Time'

```php
[
    'name' => 'Admission Fee',
    'description' => 'One-time admission fee for new students',
    'category' => 'academic',
    'is_required' => true,
    'default_frequency' => 'One-Time',
],
[
    'name' => 'Registration Fee', 
    'description' => 'One-time registration fee for new academic year',
    'category' => 'academic',
    'is_required' => true,
    'default_frequency' => 'One-Time',
],
```

## API Usage Examples

### Creating Fee Structure with One-Time Components

```json
POST /api/fee-structures

{
  "name": "Grade 1 Complete Fee Structure",
  "class_id": 1,
  "academic_year_id": 1,
  "components": [
    {
      "master_component_id": 1,
      "amount": 5000.00,
      "frequency": "Monthly",
      "required": true
    },
    {
      "master_component_id": 2,
      "amount": 15000.00,
      "frequency": "One-Time",
      "required": true
    },
    {
      "master_component_id": 5,
      "amount": 10000.00,
      "frequency": "One-Time",
      "required": true
    }
  ]
}
```

### Response Structure
```json
{
  "status": "success",
  "message": "Fee structure created successfully",
  "data": {
    "id": 1,
    "name": "Grade 1 Complete Fee Structure",
    "components": [
      {
        "id": 1,
        "amount": "5000.00",
        "frequency": "Monthly",
        "required": true,
        "master_component": {
          "name": "Tuition Fee"
        }
      },
      {
        "id": 2,
        "amount": "15000.00",
        "frequency": "One-Time",
        "required": true,
        "master_component": {
          "name": "Admission Fee"
        }
      }
    ]
  }
}
```

## Installment Generation Behavior

### One-Time Frequency Behavior
- **Number of Installments**: 1
- **Due Date**: Immediate (same as start date)
- **Amount**: Full component amount
- **Installment Number**: 1

### Comparison with Other Frequencies

| Frequency | Installments | Due Dates | Amount per Installment |
|-----------|-------------|-----------|----------------------|
| Monthly | 12 | 1st of each month | Total ÷ 12 |
| Quarterly | 4 | Every 3 months | Total ÷ 4 |
| Yearly | 1 | Start date | Full amount |
| **One-Time** | **1** | **Start date** | **Full amount** |

## Use Cases

### 1. Admission Fees
- **Frequency**: One-Time
- **When Charged**: At the time of admission
- **Amount**: Fixed admission fee
- **Required**: Usually true

### 2. Security Deposits
- **Frequency**: One-Time
- **When Charged**: At the time of admission
- **Amount**: Refundable deposit
- **Required**: School policy dependent

### 3. Registration Fees
- **Frequency**: One-Time (per academic year)
- **When Charged**: At the start of academic year
- **Amount**: Annual registration fee
- **Required**: Usually true

### 4. Equipment/Uniform Fees
- **Frequency**: One-Time (or yearly)
- **When Charged**: At the time of purchase
- **Amount**: Cost of equipment/uniforms
- **Required**: Grade/activity dependent

## Migration Commands

```bash
# Apply the new migrations
php artisan migrate

# Reseed master fee components with updated frequencies
php artisan db:seed --class=MasterFeeComponentSeeder
```

## Testing Scenarios

### 1. Create Fee Structure with One-Time Components
- Verify frequency validation accepts 'One-Time'
- Check database storage of One-Time frequency
- Confirm API response includes correct frequency

### 2. Automatic Student Plan Creation
- Create fee structure with mix of frequencies including One-Time
- Verify CreateDefaultStudentFeePlans job creates plans correctly
- Check that required One-Time components are included

### 3. Installment Generation
- Create student fee plan with One-Time components
- Verify GenerateStudentFeeInstallments job creates single installment
- Check installment due date is immediate
- Confirm installment amount equals component amount

### 4. Payment Processing
- Make payment against One-Time component installment
- Verify payment allocation works correctly
- Check completion status after full payment

## Backward Compatibility

- **Existing Data**: All existing components continue to work
- **API**: Previous frequency values ('Monthly', 'Quarterly', 'Yearly') remain supported
- **Database**: No changes to existing records required
- **Jobs**: Existing installment generation logic unchanged

## Notes

- **One-Time vs Yearly**: Both create single installments, but semantically different
  - **Yearly**: Recurring annual fee (charged every academic year)
  - **One-Time**: Single occurrence fee (admission, deposits, etc.)
- **Future Enhancement**: Could add date-specific One-Time fees (due on specific dates)
- **Reporting**: One-Time fees should be tracked separately in financial reports
