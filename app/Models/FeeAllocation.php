<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeAllocation extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'school_id',
        'fee_payment_id',
        'fee_installment_id',
        'amount',
    ];
    
    // School relationship
    public function school()
    {
        return $this->belongsTo(School::class);
    }
    
    // Payment relationship
    public function payment()
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }
    
    // Installment relationship
    public function installment()
    {
        return $this->belongsTo(FeeInstallment::class, 'fee_installment_id');
    }
    
    // School scope for data isolation
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}
