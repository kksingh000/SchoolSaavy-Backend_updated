<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeePayment extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'school_id',
        'student_id',
        'fee_installment_id',
        'amount',
        'payment_date',
        'payment_method',
        'transaction_id',
        'reference_number',
        'received_by',
        'receipt_number',
        'notes',
        'status'
    ];
    
    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];
    
    // School relationship
    public function school()
    {
        return $this->belongsTo(School::class);
    }
    
    // Student relationship
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    
    // Installment relationship
    public function installment()
    {
        return $this->belongsTo(FeeInstallment::class, 'fee_installment_id');
    }
    
    // Receiver relationship
    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
    
    // Allocations relationship
    public function allocations()
    {
        return $this->hasMany(FeeAllocation::class, 'fee_payment_id');
    }
    
    // School scope for data isolation
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
    
    // Status scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }
    
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }
}
