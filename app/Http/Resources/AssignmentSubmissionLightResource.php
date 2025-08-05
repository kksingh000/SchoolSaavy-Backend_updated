<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentSubmissionLightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'submission_status' => $this->submission_status,
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'marks_obtained' => $this->marks_obtained,
            'grade_percentage' => $this->grade_percentage,
            'grade_letter' => $this->grade_letter,
            'graded_at' => $this->graded_at?->format('Y-m-d H:i:s'),
            'is_late_submission' => $this->is_late_submission,
            'is_late' => $this->is_late,
            'can_be_graded' => $this->canBeGraded(),
            'has_content' => !empty($this->content),
            'has_attachments' => !empty($this->attachments),
            'attachment_count' => $this->getAttachmentCount(),

            // Student data (lightweight)
            'student' => $this->when($this->relationLoaded('student'), function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->first_name . ' ' . $this->student->last_name,
                    'admission_number' => $this->student->admission_number,
                    'roll_number' => $this->student->roll_number,
                ];
            }),
        ];
    }

    /**
     * Get the number of attachments without loading full data
     */
    private function getAttachmentCount(): int
    {
        if (!$this->attachments || empty($this->attachments)) {
            return 0;
        }

        if (is_array($this->attachments)) {
            return count($this->attachments);
        }

        return 0;
    }
}
