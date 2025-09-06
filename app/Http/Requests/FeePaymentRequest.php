<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by controller middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'student_fee_id' => 'required|integer|exists:student_fees,id',
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,card,online,upi',
            'transaction_id' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:pending,completed,failed,refunded',
        ];

        // Additional validation for specific payment methods
        if ($this->payment_method === 'bank_transfer' || $this->payment_method === 'online') {
            $rules['transaction_id'] = 'required|string|max:255';
        }

        if ($this->payment_method === 'cheque') {
            $rules['reference_number'] = 'required|string|max:255';
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'student_fee_id.required' => 'Student fee is required',
            'student_fee_id.exists' => 'Selected student fee is invalid',
            'amount.required' => 'Payment amount is required',
            'amount.min' => 'Payment amount must be at least 0.01',
            'amount.max' => 'Payment amount cannot exceed 999999.99',
            'payment_date.required' => 'Payment date is required',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method selected',
            'transaction_id.required' => 'Transaction ID is required for this payment method',
            'reference_number.required' => 'Reference number is required for cheque payments',
            'notes.max' => 'Notes cannot exceed 1000 characters',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation to check if payment amount doesn't exceed remaining fee amount
            if ($this->student_fee_id && $this->amount) {
                $studentFee = \App\Models\StudentFee::find($this->student_fee_id);
                
                if ($studentFee) {
                    $totalPaid = $studentFee->payments()->sum('amount');
                    $remainingAmount = $studentFee->amount - $totalPaid;
                    
                    if ($this->amount > $remainingAmount) {
                        $validator->errors()->add('amount', 
                            "Payment amount cannot exceed remaining fee amount of ₹{$remainingAmount}"
                        );
                    }
                }
            }
        });
    }
}
