# School Management System – Fee Module Design (Detailed with Class Defaults)

This document provides a **detailed database schema** and **essential APIs** for managing school fees. It is based on the **Class-level default fee plan (Option 1)** approach with the ability to override for specific students.

---

## 1. Database Schema

### **1.1 FeeStructure**

Defines the fee plan at a class level.

```sql
FeeStructure (
    fee_structure_id  PK,
    school_id         FK,
    class_id          FK,   -- Fee is defined per class
    fee_type          ENUM('Tuition','Admission','Examination','Library','Transport','Hostel','Mess','Lab','Sports','IT','Development','Misc'),
    amount            DECIMAL(10,2),
    frequency         ENUM('Monthly','Quarterly','Yearly','OneTime'),
    due_day           INT,   -- e.g., 10 means due by 10th of the month/quarter
    late_fee_per_day  DECIMAL(10,2) NULL
)
```

➡️ Each **class** has multiple fee components (e.g., Tuition, Transport, Lab, Hostel). These act as defaults.

---

### **1.2 StudentFeePlan (Overrides Only)**

Only used when a student’s plan differs from the class default (discounts, scholarships, etc.).

```sql
StudentFeePlan (
    student_fee_plan_id  PK,
    student_id           FK,
    fee_structure_id     FK,   -- Points to class-level fee
    discount_percentage  DECIMAL(5,2) NULL,
    start_date           DATE,
    end_date             DATE
)
```

➡️ Most students don’t need this table. It is only populated for **exceptions**.

---

### **1.3 FeeInstallment**

Stores all expected payments for each student. Generated automatically from **FeeStructure** and adjusted if there’s a **StudentFeePlan override**.

```sql
FeeInstallment (
    installment_id      PK,
    student_id          FK,
    fee_structure_id    FK,
    installment_no      INT,
    due_date            DATE,
    amount              DECIMAL(10,2),
    status              ENUM('Pending','Paid','Partially Paid','Overdue'),
    paid_amount         DECIMAL(10,2) DEFAULT 0,
    payment_date        DATE NULL
)
```

➡️ Auto-generated whenever a student is enrolled in a class.

---

### **1.4 Payment**

Captures actual payments made by parents.

```sql
Payment (
    payment_id     PK,
    student_id     FK,
    amount_paid    DECIMAL(10,2),
    payment_date   DATE,
    mode           ENUM('Cash','Card','UPI','Bank Transfer'),
    remarks        VARCHAR(255)
)
```

---

### **1.5 PaymentAllocation**

Distributes payment amounts across installments.

```sql
PaymentAllocation (
    allocation_id    PK,
    payment_id       FK,
    installment_id   FK,
    allocated_amount DECIMAL(10,2)
)
```

➡️ Handles **advance payments** (e.g., full year upfront) by splitting across multiple installments.

---

## 2. API Design

### **2.1 Create Fee Structure (Class Level)**

`POST /fee-structure`

```json
{
  "school_id": 1,
  "class_id": 3,
  "fee_type": "Tuition",
  "amount": 1000,
  "frequency": "Monthly",
  "due_day": 10,
  "late_fee_per_day": 20
}
```

➡️ Repeat this call for each fee component (e.g., Transport, Lab, Hostel).

---

### **2.2 Enroll Student in Class**

`POST /enroll-student`

```json
{
  "student_id": 101,
  "class_id": 3,
  "start_date": "2025-04-01",
  "end_date": "2026-03-31"
}
```

➡️ System automatically generates **FeeInstallments** using the class-level **FeeStructure**.

---

### **2.3 Override Fee Plan for a Student**

`POST /student-fee-plan`

```json
{
  "student_id": 101,
  "fee_structure_id": 5,
  "discount_percentage": 15,
  "start_date": "2025-04-01",
  "end_date": "2026-03-31"
}
```

➡️ Overrides the default fee plan with discounts/special conditions. Installments are recalculated.

---

### **2.4 Record Payment**

`POST /payment`

```json
{
  "student_id": 101,
  "amount_paid": 12000,
  "payment_date": "2025-04-05",
  "mode": "UPI",
  "remarks": "Full year advance"
}
```

➡️ Payment is recorded and automatically allocated to pending installments.

---

### **2.5 Get Student Fee Status**

`GET /student/{id}/fee-status`

```json
{
  "student_id": 101,
  "installments": [
    {"installment_no": 1, "due_date": "2025-04-10", "amount": 1000, "status": "Paid"},
    {"installment_no": 2, "due_date": "2025-05-10", "amount": 1000, "status": "Paid"},
    {"installment_no": 3, "due_date": "2025-06-10", "amount": 1000, "status": "Paid"}
  ],
  "overall_status": "Up to date"
}
```

---

### **2.6 Late Fee & Reminder API**

`GET /reminders/late-fees`

```json
[
  {
    "student_id": 102,
    "installment_id": 33,
    "due_date": "2025-05-10",
    "days_overdue": 5,
    "late_fee": 100
  }
]
```

---

## 3. Example Scenarios

### **Monthly Tuition Fee (₹1000 × 12)**

* Class 3 has Tuition Fee = ₹1000 monthly.
* A student enrolled in April gets 12 installments auto-generated (Apr–Mar).
* Parent pays ₹12,000 upfront → Allocated across 12 installments.

### **Quarterly Transport Fee (₹3000 × 4)**

* Class 5 has Transport Fee = ₹3000 quarterly.
* Student gets 4 installments: Apr, Jul, Oct, Jan.
* Parent pays quarterly or in advance.

### **Yearly Lab Fee (₹12,000 × 1)**

* Class 7 has Lab Fee = ₹12,000 yearly.
* Student has 1 installment due on 10-Apr.
* If unpaid beyond 10-Apr, late fees apply daily until paid.

### **One-time Admission Fee (₹5000)**

* Class 1 has Admission Fee = ₹5000 (OneTime).
* Student charged once at enrollment.
* No further installments generated.

---

## 4. Key Advantages of Class-Default Model

* **Less data entry**: Define all components once per class.
* **Automatic scalability**: New students inherit all fee components instantly.
* **Flexible overrides**: Discounts, scholarships, or exemptions handled with StudentFeePlan.
* **Comprehensive structure**: Covers all common fee components (Tuition, Admission, Examination, Library, Transport, Hostel, Mess, Lab, Sports, IT, Development, Misc).

---

✅ This design provides a full-fledged fee module that supports multiple fee components, payment modes, advance payments, and late fee management.
