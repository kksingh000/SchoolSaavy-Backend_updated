<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'student_id',
        'amount',
        'method',
        'date',
        'status',
        'transaction_id',
        'notes',
    ];

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function getAllocatedAmountAttribute()
    {
        return $this->allocations()->sum('amount');
    }

    public function getUnallocatedAmountAttribute()
    {
        return $this->amount - $this->allocated_amount;
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'Success');
    }
}
