<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'method' => ['required', 'string', Rule::in(['Cash', 'UPI', 'Card', 'BankTransfer'])],
            'date' => 'nullable|date',
            'status' => ['nullable', 'string', Rule::in(['Success', 'Failed', 'Pending'])],
            'transaction_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required',
            'student_id.exists' => 'Selected student does not exist',
            'amount.required' => 'Payment amount is required',
            'amount.numeric' => 'Payment amount must be a valid number',
            'amount.min' => 'Payment amount must be at least 0.01',
            'amount.max' => 'Payment amount cannot exceed 999999.99',
            'method.required' => 'Payment method is required',
            'method.in' => 'Payment method must be one of: Cash, UPI, Card, BankTransfer',
            'date.date' => 'Payment date must be a valid date',
            'status.in' => 'Payment status must be one of: Success, Failed, Pending',
            'transaction_id.max' => 'Transaction ID cannot exceed 255 characters',
            'notes.max' => 'Notes cannot exceed 1000 characters',
        ];
    }

    /**
     * Additional validation checks
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $schoolId = request()->input('school_id');

            // Validate student belongs to school
            if ($this->has('student_id')) {
                $studentExists = \App\Models\Student::where('id', $this->student_id)
                    ->where('school_id', $schoolId)
                    ->exists();

                if (!$studentExists) {
                    $validator->errors()->add(
                        'student_id',
                        'Selected student does not belong to your school'
                    );
                }
            }
        });
    }
}
