<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\SchoolSetting;
use App\Http\Controllers\BaseController;
use App\Http\Requests\AdmissionNumber\GenerateBatchRequest;
use App\Http\Requests\AdmissionNumber\UpdateSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdmissionNumberController extends BaseController
{
    /**
     * Generate next admission number for the school
     */
    public function generate(Request $request): JsonResponse
    {
        try {
            $schoolId = request()->school_id;

            // Get admission number settings
            $prefix = SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $format = SchoolSetting::getSetting($schoolId, 'admission_number_format', 'sequential');
            $startFrom = SchoolSetting::getSetting($schoolId, 'admission_number_start_from', 1);
            $includeYear = SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
            $yearFormat = SchoolSetting::getSetting($schoolId, 'admission_number_year_format', 'YYYY');
            $paddingLength = SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            // Generate the admission number
            $admissionNumber = $this->generateAdmissionNumber(
                $schoolId,
                $prefix,
                $format,
                $startFrom,
                $includeYear,
                $yearFormat,
                $paddingLength
            );

            return $this->successResponse([
                'admission_number' => $admissionNumber,
                'settings' => [
                    'prefix' => $prefix,
                    'format' => $format,
                    'include_year' => $includeYear,
                    'year_format' => $yearFormat,
                    'padding_length' => $paddingLength
                ],
                'preview' => $this->generatePreviewNumbers($schoolId, $prefix, $format, $startFrom, $includeYear, $yearFormat, $paddingLength, 3)
            ], 'Admission number generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Generate multiple admission numbers (for bulk operations)
     */
    public function generateBatch(GenerateBatchRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $count = $data['count'];
            $schoolId = request()->school_id;

            // Get admission number settings
            $prefix = SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $format = SchoolSetting::getSetting($schoolId, 'admission_number_format', 'sequential');
            $startFrom = SchoolSetting::getSetting($schoolId, 'admission_number_start_from', 1);
            $includeYear = SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
            $yearFormat = SchoolSetting::getSetting($schoolId, 'admission_number_year_format', 'YYYY');
            $paddingLength = SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            // Generate multiple admission numbers
            $admissionNumbers = [];
            $nextSequenceNumber = $this->getNextSequentialNumber($schoolId, $format, $startFrom);

            for ($i = 0; $i < $count; $i++) {
                $number = '';

                // Add prefix if provided
                if ($prefix) {
                    $number .= $prefix;
                }

                // Add year if required
                if ($includeYear) {
                    $year = now()->format($yearFormat === 'YY' ? 'y' : 'Y');
                    $number .= $year;
                }

                // Add sequential number with padding
                $paddedNumber = str_pad($nextSequenceNumber + $i, $paddingLength, '0', STR_PAD_LEFT);
                $number .= $paddedNumber;

                // Verify uniqueness
                if (!Student::where('school_id', $schoolId)->where('admission_number', $number)->exists()) {
                    $admissionNumbers[] = $number;
                } else {
                    // If collision occurs, regenerate individually
                    $uniqueNumber = $this->generateAdmissionNumber($schoolId, $prefix, $format, $nextSequenceNumber + $i + 1, $includeYear, $yearFormat, $paddingLength);
                    $admissionNumbers[] = $uniqueNumber;
                }
            }

            return $this->successResponse([
                'admission_numbers' => $admissionNumbers,
                'count' => count($admissionNumbers),
                'settings' => [
                    'prefix' => $prefix,
                    'format' => $format,
                    'include_year' => $includeYear,
                    'year_format' => $yearFormat,
                    'padding_length' => $paddingLength
                ]
            ], 'Batch admission numbers generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Check if admission number is available
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        try {
            $admissionNumber = $request->get('admission_number');

            if (!$admissionNumber) {
                return $this->errorResponse('Admission number is required', null, 422);
            }

            $schoolId = request()->school_id;

            $exists = Student::where('school_id', $schoolId)
                ->where('admission_number', $admissionNumber)
                ->exists();

            return $this->successResponse([
                'admission_number' => $admissionNumber,
                'available' => !$exists,
                'message' => $exists ? 'Admission number is already taken' : 'Admission number is available'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get admission number settings
     */
    public function getSettings(): JsonResponse
    {
        try {
            $schoolId = request()->school_id;

            // Get last generated admission number from database
            $lastStudent = Student::where('school_id', $schoolId)
                ->orderBy('created_at', 'desc')
                ->first();
            $lastGenerated = $lastStudent ? $lastStudent->admission_number : null;

            // Get current settings
            $prefix = SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $format = SchoolSetting::getSetting($schoolId, 'admission_number_format', 'sequential');
            $startFrom = SchoolSetting::getSetting($schoolId, 'admission_number_start_from', 1);
            $includeYear = SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
            $yearFormat = SchoolSetting::getSetting($schoolId, 'admission_number_year_format', 'YYYY');
            $paddingLength = SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            // Count students with new format vs old format
            $studentsWithNewFormat = Student::where('school_id', $schoolId)
                ->where('admission_number', 'LIKE', $prefix . '%')
                ->count();

            $settings = [
                'prefix' => $prefix,
                'format' => $format,
                'start_from' => $startFrom,
                'include_year' => $includeYear,
                'year_format' => $yearFormat,
                'padding_length' => $paddingLength,
                'next_number' => $this->getCurrentAdmissionNumber($schoolId),
                'last_generated' => $lastGenerated,
                'last_generated_note' => $lastGenerated && !str_starts_with($lastGenerated, $prefix)
                    ? 'This was generated before current settings were applied'
                    : 'Generated with current settings',
                'total_students' => Student::where('school_id', $schoolId)->count(),
                'students_with_new_format' => $studentsWithNewFormat,
                'migration_status' => $studentsWithNewFormat > 0 ? 'partial' : 'not_started'
            ];

            return $this->successResponse($settings, 'Admission number settings retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update admission number settings
     */
    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        try {
            $schoolId = request()->school_id;
            $data = $request->validated();

            $settings = [];

            foreach ($data as $key => $value) {
                $settingKey = 'admission_number_' . $key;
                $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');

                SchoolSetting::setSetting(
                    $schoolId,
                    $settingKey,
                    $value,
                    $type,
                    'admission',
                    $this->getSettingDescription($key)
                );

                $settings[$key] = $value;
            }

            return $this->successResponse($settings, 'Admission number settings updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Migrate existing students to new admission number format (optional)
     */
    public function migrateExistingNumbers(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'confirm' => 'required|boolean|accepted',
                'preview_only' => 'sometimes|boolean'
            ]);

            $schoolId = request()->school_id;
            $previewOnly = $validatedData['preview_only'] ?? false;

            // Get current settings
            $prefix = SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $format = SchoolSetting::getSetting($schoolId, 'admission_number_format', 'sequential');
            $startFrom = SchoolSetting::getSetting($schoolId, 'admission_number_start_from', 1);
            $includeYear = SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
            $yearFormat = SchoolSetting::getSetting($schoolId, 'admission_number_year_format', 'YYYY');
            $paddingLength = SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            // Find students with old format (not matching current prefix)
            $studentsToMigrate = Student::where('school_id', $schoolId)
                ->where('admission_number', 'NOT LIKE', $prefix . '%')
                ->whereNotNull('admission_number')
                ->orderBy('created_at')
                ->get();

            if ($studentsToMigrate->isEmpty()) {
                return $this->successResponse([
                    'message' => 'No students need migration. All admission numbers already follow the current format.',
                    'total_students' => Student::where('school_id', $schoolId)->count(),
                    'students_to_migrate' => 0
                ]);
            }

            $migrationPreview = [];
            $nextSequenceNumber = $this->getNextSequentialNumber($schoolId, $format, $startFrom);

            foreach ($studentsToMigrate as $index => $student) {
                $newNumber = '';

                // Add prefix if provided
                if ($prefix) {
                    $newNumber .= $prefix;
                }

                // Add year if required
                if ($includeYear) {
                    // Use student's admission year or current year
                    $studentYear = $student->admission_date ?
                        \Carbon\Carbon::parse($student->admission_date)->format($yearFormat === 'YY' ? 'y' : 'Y') :
                        now()->format($yearFormat === 'YY' ? 'y' : 'Y');
                    $newNumber .= $studentYear;
                }

                // Add sequential number with padding
                $sequenceNumber = $nextSequenceNumber + $index;
                $paddedNumber = str_pad($sequenceNumber, $paddingLength, '0', STR_PAD_LEFT);
                $newNumber .= $paddedNumber;

                // Ensure uniqueness
                while (Student::where('school_id', $schoolId)->where('admission_number', $newNumber)->exists()) {
                    $sequenceNumber++;
                    $paddedNumber = str_pad($sequenceNumber, $paddingLength, '0', STR_PAD_LEFT);
                    $newNumber = ($prefix ?? '') . ($includeYear ? ($studentYear ?? now()->format($yearFormat === 'YY' ? 'y' : 'Y')) : '') . $paddedNumber;
                }

                $migrationPreview[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'old_number' => $student->admission_number,
                    'new_number' => $newNumber,
                    'admission_date' => $student->admission_date
                ];

                // If not preview only, perform the migration
                if (!$previewOnly) {
                    $student->update(['admission_number' => $newNumber]);
                }
            }

            $message = $previewOnly ?
                'Migration preview generated successfully' :
                'Students migrated to new admission number format successfully';

            return $this->successResponse([
                'preview_only' => $previewOnly,
                'students_migrated' => count($migrationPreview),
                'migration_details' => $migrationPreview,
                'new_format_example' => $prefix . ($includeYear ? now()->format($yearFormat === 'YY' ? 'y' : 'Y') : '') . str_pad(1, $paddingLength, '0', STR_PAD_LEFT)
            ], $message);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Generate admission number based on school settings
     */
    private function generateAdmissionNumber($schoolId, $prefix, $format, $startFrom, $includeYear, $yearFormat, $paddingLength): string
    {
        $maxAttempts = 1000; // Prevent infinite loops
        $attempts = 0;

        do {
            $number = '';

            // Add prefix if provided
            if ($prefix) {
                $number .= $prefix;
            }

            // Add year if required
            if ($includeYear) {
                $year = now()->format($yearFormat === 'YY' ? 'y' : 'Y');
                $number .= $year;
            }

            // Get next sequential number (adjusted for current attempt)
            $sequentialNumber = $this->getNextSequentialNumber($schoolId, $format, $startFrom) + $attempts;

            // Pad the number
            $paddedNumber = str_pad($sequentialNumber, $paddingLength, '0', STR_PAD_LEFT);
            $number .= $paddedNumber;

            // Check if this number already exists
            $exists = Student::where('school_id', $schoolId)
                ->where('admission_number', $number)
                ->exists();

            if (!$exists) {
                return $number;
            }

            $attempts++;
        } while ($attempts < $maxAttempts);

        // If we couldn't generate a unique number, throw an exception
        throw new \Exception("Could not generate a unique admission number after {$maxAttempts} attempts");
    }

    /**
     * Get next sequential number based on existing students
     */
    private function getNextSequentialNumber($schoolId, $format, $startFrom): int
    {
        if ($format === 'year_sequential') {
            // Reset sequence each year - get max sequence number for current year
            $year = now()->year;

            // Extract sequence numbers from existing admission numbers for current year
            $prefix = SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $includeYear = SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
            $yearFormat = SchoolSetting::getSetting($schoolId, 'admission_number_year_format', 'YYYY');
            $paddingLength = SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            $yearString = '';
            if ($includeYear) {
                $yearString = now()->format($yearFormat === 'YY' ? 'y' : 'Y');
            }

            // Get all students with admission numbers from current year
            $students = Student::where('school_id', $schoolId)
                ->whereYear('created_at', $year)
                ->whereNotNull('admission_number')
                ->pluck('admission_number');

            $maxSequenceNumber = 0;
            foreach ($students as $admissionNumber) {
                // Extract sequence number from admission number
                $sequenceNumber = $this->extractSequenceNumber($admissionNumber, $prefix, $yearString, $paddingLength);
                if ($sequenceNumber > $maxSequenceNumber) {
                    $maxSequenceNumber = $sequenceNumber;
                }
            }

            return max($maxSequenceNumber + 1, $startFrom);
        } else {
            // Continuous sequence - get max sequence number across all years
            $prefix = SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $includeYear = SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
            $paddingLength = SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            $students = Student::where('school_id', $schoolId)
                ->whereNotNull('admission_number')
                ->pluck('admission_number');

            $maxSequenceNumber = 0;
            foreach ($students as $admissionNumber) {
                // For continuous sequence, we need to handle different year formats
                if ($includeYear) {
                    // Try both YYYY and YY formats to find the sequence number
                    $currentYear = now()->format('Y');
                    $shortYear = now()->format('y');

                    // Try current year first
                    $sequenceNumber = $this->extractSequenceNumber($admissionNumber, $prefix, $currentYear, $paddingLength);
                    if ($sequenceNumber === 0) {
                        $sequenceNumber = $this->extractSequenceNumber($admissionNumber, $prefix, $shortYear, $paddingLength);
                    }

                    // Try extracting from previous years as well for continuous sequence
                    if ($sequenceNumber === 0) {
                        for ($year = 2020; $year <= now()->year; $year++) {
                            $yearStr = str_pad($year, 4, '0', STR_PAD_LEFT);
                            $shortYearStr = substr($yearStr, -2);

                            $sequenceNumber = $this->extractSequenceNumber($admissionNumber, $prefix, $yearStr, $paddingLength);
                            if ($sequenceNumber === 0) {
                                $sequenceNumber = $this->extractSequenceNumber($admissionNumber, $prefix, $shortYearStr, $paddingLength);
                            }

                            if ($sequenceNumber > 0) break;
                        }
                    }
                } else {
                    $sequenceNumber = $this->extractSequenceNumber($admissionNumber, $prefix, '', $paddingLength);
                }

                if ($sequenceNumber > $maxSequenceNumber) {
                    $maxSequenceNumber = $sequenceNumber;
                }
            }

            return max($maxSequenceNumber + 1, $startFrom);
        }
    }

    /**
     * Extract sequence number from admission number
     */
    private function extractSequenceNumber($admissionNumber, $prefix, $yearString, $paddingLength): int
    {
        // Remove prefix
        if ($prefix && str_starts_with($admissionNumber, $prefix)) {
            $admissionNumber = substr($admissionNumber, strlen($prefix));
        }

        // Remove year if present
        if ($yearString && str_starts_with($admissionNumber, $yearString)) {
            $admissionNumber = substr($admissionNumber, strlen($yearString));
        }

        // The remaining should be the sequence number
        if (is_numeric($admissionNumber)) {
            return (int)$admissionNumber;
        }

        return 0;
    }

    /**
     * Generate preview admission numbers
     */
    private function generatePreviewNumbers($schoolId, $prefix, $format, $startFrom, $includeYear, $yearFormat, $paddingLength, $count): array
    {
        $previewNumbers = [];
        $nextSequenceNumber = $this->getNextSequentialNumber($schoolId, $format, $startFrom);

        for ($i = 0; $i < $count; $i++) {
            $number = '';

            // Add prefix if provided
            if ($prefix) {
                $number .= $prefix;
            }

            // Add year if required
            if ($includeYear) {
                $year = now()->format($yearFormat === 'YY' ? 'y' : 'Y');
                $number .= $year;
            }

            // Add sequential number with padding
            $paddedNumber = str_pad($nextSequenceNumber + $i, $paddingLength, '0', STR_PAD_LEFT);
            $number .= $paddedNumber;

            $previewNumbers[] = $number;
        }

        return $previewNumbers;
    }

    /**
     * Get current/next expected admission number based on settings
     */
    private function getCurrentAdmissionNumber($schoolId): ?string
    {
        try {
            // Get admission number settings
            $prefix = SchoolSetting::getSetting($schoolId, 'admission_number_prefix', '');
            $format = SchoolSetting::getSetting($schoolId, 'admission_number_format', 'sequential');
            $startFrom = SchoolSetting::getSetting($schoolId, 'admission_number_start_from', 1);
            $includeYear = SchoolSetting::getSetting($schoolId, 'admission_number_include_year', false);
            $yearFormat = SchoolSetting::getSetting($schoolId, 'admission_number_year_format', 'YYYY');
            $paddingLength = SchoolSetting::getSetting($schoolId, 'admission_number_padding', 4);

            // Generate what the next admission number would be
            return $this->generateAdmissionNumber(
                $schoolId,
                $prefix,
                $format,
                $startFrom,
                $includeYear,
                $yearFormat,
                $paddingLength
            );
        } catch (\Exception $e) {
            // If generation fails, return the last student's admission number
            $lastStudent = Student::where('school_id', $schoolId)
                ->orderBy('created_at', 'desc')
                ->first();

            return $lastStudent ? $lastStudent->admission_number : null;
        }
    }

    /**
     * Get setting description
     */
    private function getSettingDescription($key): string
    {
        return match ($key) {
            'prefix' => 'Prefix for admission numbers (e.g., "STU", "SCH")',
            'format' => 'Format for number generation: sequential (continuous) or year_sequential (resets yearly)',
            'start_from' => 'Starting number for sequence',
            'include_year' => 'Include current year in admission number',
            'year_format' => 'Year format: YYYY (2025) or YY (25)',
            'padding_length' => 'Number of digits for the sequential part (with zero padding)',
            default => 'Admission number setting'
        };
    }
}
