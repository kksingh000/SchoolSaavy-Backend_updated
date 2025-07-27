<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'attachments' => $this->attachments,
            'status' => $this->status,
            'submission_status' => $this->submission_status,
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'marks_obtained' => $this->marks_obtained,
            'grade_percentage' => $this->grade_percentage,
            'grade_letter' => $this->grade_letter,
            'teacher_feedback' => $this->teacher_feedback,
            'grading_details' => $this->grading_details,
            'graded_at' => $this->graded_at?->format('Y-m-d H:i:s'),
            'is_late_submission' => $this->is_late_submission,
            'is_late' => $this->is_late,
            'can_be_edited' => $this->canBeEdited(),
            'can_be_graded' => $this->canBeGraded(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Related data
            'student' => $this->when($this->relationLoaded('student'), function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->first_name . ' ' . $this->student->last_name,
                    'first_name' => $this->student->first_name,
                    'last_name' => $this->student->last_name,
                    'admission_number' => $this->student->admission_number,
                    'roll_number' => $this->student->roll_number,
                ];
            }),

            'assignment' => $this->when($this->relationLoaded('assignment'), function () {
                return [
                    'id' => $this->assignment->id,
                    'title' => $this->assignment->title,
                    'type' => $this->assignment->type,
                    'max_marks' => $this->assignment->max_marks,
                    'due_date' => $this->assignment->due_date->format('Y-m-d'),
                    'due_time' => $this->assignment->due_time ? $this->assignment->due_time->format('H:i') : null,
                ];
            }),

            'graded_by' => $this->when($this->relationLoaded('gradedBy'), function () {
                return [
                    'id' => $this->gradedBy->id,
                    'name' => $this->gradedBy->user->name,
                ];
            }),
        ];
    }
}
