# School Management System – Fee Module (Detailed with Components & Overrides)

## 1. Database Schema

### 1.1 FeeStructure

Defines standard fee setup per class for an academic year.

```sql
FeeStructure (
  id INT PK,
  class_id INT FK -> Class,
  academic_year VARCHAR,
  created_at DATETIME
)
```

### 1.2 FeeStructureComponent

Breakdown of fee components under a FeeStructure.

```sql
FeeStructureComponent (
  id INT PK,
  fee_structure_id INT FK -> FeeStructure,
  component_name ENUM('Tuition','Transport','Lab','Misc'),
  amount DECIMAL(10,2),
  frequency ENUM('Monthly','Quarterly','Yearly')
)
```

### 1.3 StudentFeePlan

Represents a student’s enrollment in a class and links to default FeeStructure.

```sql
StudentFeePlan (
  id INT PK,
  student_id INT FK -> Student,
  fee_structure_id INT FK -> FeeStructure,
  start_date DATE,
  end_date DATE NULL,
  created_at DATETIME
)
```

### 1.4 StudentFeePlanComponents (NEW)

Overrides default fee components per student (optional/discounted components).

```sql
StudentFeePlanComponents (
  id INT PK,
  student_fee_plan_id INT FK -> StudentFeePlan,
  component_id INT FK -> FeeStructureComponent,
  is_active BOOLEAN DEFAULT TRUE,
  custom_amount DECIMAL(10,2) NULL
)
```

* `is_active = FALSE` → component skipped for that student.
* `custom_amount` → override the component fee.

### 1.5 FeeInstallment

Stores all expected payments for each student. Generated automatically from FeeStructure and StudentFeePlanComponents.

```sql
FeeInstallment (
  id INT PK,
  student_fee_plan_id INT FK -> StudentFeePlan,
  component_id INT FK -> FeeStructureComponent,
  installment_no INT,
  due_date DATE,
  amount DECIMAL(10,2),
  status ENUM('Pending','Paid','Overdue'),
  paid_amount DECIMAL(10,2) DEFAULT 0
)
```

### 1.6 Payment

Captures actual payments made by parents.

```sql
Payment (
  id INT PK,
  student_id INT FK -> Student,
  amount DECIMAL(10,2),
  method ENUM('Cash','UPI','Card','BankTransfer'),
  date DATETIME,
  status ENUM('Success','Failed','Pending')
)
```

### 1.7 PaymentAllocation

Links each payment to one or more installments.

```sql
PaymentAllocation (
  id INT PK,
  payment_id INT FK -> Payment,
  installment_id INT FK -> FeeInstallment,
  amount DECIMAL(10,2)
)
```

---

## 2. API Design

### 2.1 Create Fee Structure

`POST /fee-structure`

```json
{
  "class_id": 3,
  "academic_year": "2025-26",
  "components": [
    { "name": "Tuition", "amount": 12000, "frequency": "Yearly" },
    { "name": "Transport", "amount": 6000, "frequency": "Yearly" },
    { "name": "Lab", "amount": 2000, "frequency": "Yearly" },
    { "name": "Misc", "amount": 1000, "frequency": "Quarterly" }
  ]
}
```

### 2.2 Assign Fee Plan to Student

`POST /student-fee-plan`

```json
{
  "student_id": "S123",
  "fee_structure_id": 1,
  "components": [
    { "component_id": 1, "is_active": true },
    { "component_id": 2, "is_active": false },
    { "component_id": 3, "is_active": true, "custom_amount": 1500 },
    { "component_id": 4, "is_active": true }
  ]
}
```

This example: Tuition active, Transport skipped, Lab discounted to 1500, Misc active.

### 2.3 Record Payment (with auto-allocation)

`POST /payment`

```json
{
  "student_id": "S123",
  "amount": 1000,
  "method": "UPI"
}
```

* System inserts into **Payment**.
* Allocation engine applies it to oldest pending **FeeInstallment**.
* Creates **PaymentAllocation** entries.

---

## 3. Installment Automation

* **On student enrollment**: generate installments using FeeStructure + StudentFeePlanComponents.
* **On StudentFeePlan update**: regenerate installments for that student.
* **On FeeStructure update**: generate only new installments from change date.

Implementation: best as a background job or event-driven workflow (e.g., `STUDENT_ENROLLED`, `STUDENT_FEE_PLAN_UPDATED`).

---

## 4. Example Scenarios

### Student A (Class 5, Tuition + Lab only)

* Transport inactive.
* Installments generated: Tuition (12,000), Lab (2,000).

### Student B (Class 5, Tuition + Transport with discount)

* Tuition active.
* Transport active with `custom_amount = 4000`.
* Lab inactive.
* Installments generated: Tuition (12,000), Transport (4,000).

### Student C (Class 5, all components)

* Tuition (12,000), Transport (6,000), Lab (2,000), Misc (1,000 quarterly).

---

✅ This structure allows defaults at the class level, with per-student flexibility via `StudentFeePlanComponents`. Optional components (like Transport, Lab) are elegantly handled by toggling `is_active` or setting custom amounts.
