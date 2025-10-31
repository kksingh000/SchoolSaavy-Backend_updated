<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'type' => $this->type,
            'status' => $this->status,
            'assigned_date' => $this->assigned_date->format('Y-m-d'),
            'due_date' => $this->due_date->format('Y-m-d'),
            'due_time' => $this->due_time ? $this->due_time->format('H:i') : null,
            'max_marks' => $this->max_marks,
            'attachments' => $this->formatted_attachments,
            'allow_late_submission' => $this->allow_late_submission,
            'grading_criteria' => $this->grading_criteria,
            'is_active' => $this->is_active,
            'is_overdue' => $this->is_overdue,
            'days_until_due' => $this->days_until_due,
            'can_be_edited' => $this->canBeEdited(),
            'can_be_deleted' => $this->canBeDeleted(),
            'can_accept_submissions' => $this->canAcceptSubmissions(),
            'submission_stats' => $this->submission_stats,
            'class_performance' => $this->getClassPerformance(),
            'average_marks' => $this->getAverageMarks(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Related data
            'teacher' => $this->when($this->relationLoaded('teacher'), function () {
                return [
                    'id' => $this->teacher->id,
                    'name' => $this->teacher->user->name,
                    'email' => $this->teacher->user->email,
                ];
            }),

            'class' => $this->when($this->relationLoaded('class'), function () {
                return [
                    'id' => $this->class->id,
                    'name' => $this->class->name,
                    'section' => $this->class->section,
                    'grade_level' => $this->class->grade_level,
                ];
            }),

            'subject' => $this->when($this->relationLoaded('subject'), function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                    'code' => $this->subject->code,
                ];
            }),

            'submissions' => $this->when($this->relationLoaded('submissions'), function () {
                return AssignmentSubmissionResource::collection($this->submissions);
            }),
        ];
    }
}
