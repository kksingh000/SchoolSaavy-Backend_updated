<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'student_fee_plan_id',
        'component_id',
        'installment_no',
        'due_date',
        'amount',
        'status',
        'paid_amount',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function studentFeePlan()
    {
        return $this->belongsTo(StudentFeePlan::class);
    }

    public function component()
    {
        return $this->belongsTo(FeeStructureComponent::class, 'component_id');
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'installment_id');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->amount - $this->paid_amount;
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'Overdue');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'Paid');
    }
}
