<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FeeStructureResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'class_id' => $this->class_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', function() {
                return new AcademicYearResource($this->academicYear);
            }),
            'components' => $this->whenLoaded('components', function() {
                return $this->components;
            }, []),
            'fee_components' => $this->when(isset($this->fee_components), $this->fee_components),
            'total_amount' => $this->when(isset($this->total_amount), $this->total_amount),
            'is_active' => $this->when(isset($this->is_active), $this->is_active, true),
            'description' => $this->description,
            'school' => $this->whenLoaded('school', function() {
                return new SchoolResource($this->school);
            }),
            'class' => $this->whenLoaded('class', function() {
                return new ClassResource($this->class);
            }),
            'student_fees' => $this->whenLoaded('studentFees', function() {
                return StudentFeeResource::collection($this->studentFees);
            }),
            'student_fees_count' => $this->whenLoaded('studentFees', function () {
                return $this->studentFees->count();
            }),
            'total_students' => $this->whenLoaded('studentFees', function () {
                return $this->studentFees->pluck('student_id')->unique()->count();
            }),
            'total_amount_due' => $this->whenLoaded('studentFees', function () {
                return $this->studentFees->sum('amount');
            }),
            'total_amount_paid' => $this->whenLoaded('studentFees', function () {
                return $this->studentFees->sum(function ($fee) {
                    return $fee->payments->sum('amount');
                });
            }),
            'collection_rate' => $this->whenLoaded('studentFees', function () {
                $totalDue = $this->studentFees->sum('amount');
                $totalPaid = $this->studentFees->sum(function ($fee) {
                    return $fee->payments->sum('amount');
                });
                return $totalDue > 0 ? round(($totalPaid / $totalDue) * 100, 2) : 0;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
