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
            'attachments' => $this->formatAttachments(),
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

    /**
     * Format attachments to include downloadable URLs and metadata
     */
    private function formatAttachments()
    {
        if (!$this->attachments || empty($this->attachments)) {
            return [];
        }

        // If attachments is already an array with proper structure, return as is
        if (is_array($this->attachments) && isset($this->attachments[0]['url'])) {
            return collect($this->attachments)->map(function ($attachment) {
                $baseUrl = url('/api');
                $downloadUrl = $baseUrl . '/assignment-submissions/' . $this->id . '/download?filename=' . urlencode($attachment['filename'] ?? '');
                $viewUrl = $baseUrl . '/assignment-submissions/' . $this->id . '/download?filename=' . urlencode($attachment['filename'] ?? '') . '&action=view';

                return [
                    'name' => $attachment['name'] ?? 'Unknown',
                    'filename' => $attachment['filename'] ?? $attachment['name'] ?? 'unknown',
                    'url' => $attachment['url'] ?? null,
                    'download_url' => $downloadUrl,
                    'view_url' => $viewUrl,
                    'path' => $attachment['path'] ?? null,
                    'type' => $attachment['type'] ?? null,
                    'mime_type' => $attachment['mime_type'] ?? null,
                    'size' => $attachment['size'] ?? null,
                    'size_human' => $attachment['size_human'] ?? null,
                    'uploaded_at' => $attachment['uploaded_at'] ?? null,
                ];
            })->toArray();
        }

        // Handle legacy format (if attachments contains just file paths or names)
        if (is_array($this->attachments)) {
            return collect($this->attachments)->map(function ($attachment) {
                $baseUrl = url('/api');
                if (is_string($attachment)) {
                    // Simple string file path/name
                    $filename = basename($attachment);
                    $downloadUrl = $baseUrl . '/assignment-submissions/' . $this->id . '/download?filename=' . urlencode($filename);
                    $viewUrl = $baseUrl . '/assignment-submissions/' . $this->id . '/download?filename=' . urlencode($filename) . '&action=view';

                    return [
                        'name' => $filename,
                        'filename' => $filename,
                        'url' => asset('storage/' . ltrim($attachment, '/')),
                        'download_url' => $downloadUrl,
                        'view_url' => $viewUrl,
                        'path' => $attachment,
                        'type' => pathinfo($attachment, PATHINFO_EXTENSION),
                        'mime_type' => null,
                        'size' => null,
                        'size_human' => null,
                        'uploaded_at' => $this->created_at?->toISOString(),
                    ];
                }
                return $attachment;
            })->toArray();
        }

        return [];
    }
}
