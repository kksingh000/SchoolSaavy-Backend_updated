<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'fee_structure_id',
        'component_type',
        'component_name',
        'amount',
        'due_date',
        'status',
        'concession_amount',
        'fine_amount',
        'notes',
        'is_mandatory',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'concession_amount' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'is_mandatory' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function payments()
    {
        return $this->hasMany(FeePayment::class);
    }

    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->amount - $this->total_paid;
    }

    public function getIsOverdueAttribute()
    {
        return $this->due_date < now() && $this->status !== 'paid';
    }
}
