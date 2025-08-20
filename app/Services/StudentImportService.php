<?php

namespace App\Services;

use App\Models\StudentImport;
use App\Models\ClassRoom;
use App\Jobs\ProcessStudentImport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class StudentImportService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = StudentImport::class;
    }

    public function initiateImport(string $filePath, string $fileName): StudentImport
    {
        // Validate that the file exists in storage
        if (!Storage::exists($filePath)) {
            throw new \Exception('Upload file not found. Please upload the file first using the file upload API.');
        }

        // Validate file is CSV
        $this->validateUploadedFile($filePath, $fileName);

        // Get file size
        $fileSize = Storage::size($filePath);

        // Create import record
        $studentImport = StudentImport::create([
            'school_id' => request()->school_id,
            'user_id' => Auth::id(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'status' => 'pending',
        ]);

        // Dispatch job to process import
        ProcessStudentImport::dispatch($studentImport);

        return $studentImport;
    }

    public function getImportHistory($perPage = 15)
    {
        return StudentImport::forSchool(request()->school_id)
            ->with(['user:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getImportById($id): StudentImport
    {
        return StudentImport::forSchool(request()->school_id)
            ->with(['user:id,name', 'errors'])
            ->findOrFail($id);
    }

    public function getImportErrors($id, $perPage = 50)
    {
        $import = $this->getImportById($id);

        return $import->errors()
            ->orderBy('row_number')
            ->paginate($perPage);
    }

    public function cancelImport($id): bool
    {
        $import = $this->getImportById($id);

        if (!in_array($import->status, ['pending', 'processing'])) {
            throw new \Exception('Cannot cancel import that is not pending or processing');
        }

        $import->update(['status' => 'cancelled']);

        return true;
    }

    public function deleteImport($id): bool
    {
        $import = $this->getImportById($id);

        // Delete associated file
        if (Storage::exists($import->file_path)) {
            Storage::delete($import->file_path);
        }

        // Delete import record (will cascade delete errors)
        $import->delete();

        return true;
    }

    public function generateTemplate(): array
    {
        $schoolId = request()->school_id;

        // Get sample classes for the school
        $classes = ClassRoom::where('school_id', $schoolId)
            ->where('is_active', true)
            ->limit(3)
            ->get(['name', 'section']);

        // Create template with headers and sample data
        $template = [];

        // Headers
        $template[] = [
            'admission_number',
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'admission_date',
            'blood_group',
            'address',
            'phone',
            'class_name',
            'class_section',
            'roll_number',
            'father_name',
            'father_email',
            'father_phone',
            'father_occupation',
            'father_address',
            'mother_name',
            'mother_email',
            'mother_phone',
            'mother_occupation',
            'mother_address',
            'guardian_name',
            'guardian_email',
            'guardian_phone',
            'guardian_relationship',
            'guardian_occupation',
            'guardian_address'
        ];

        // Sample data rows
        $sampleData = [
            [
                'STU001',
                'John',
                'Doe',
                '2015-05-15',
                'male',
                '2024-04-01',
                'A+',
                '123 Main Street, City',
                '9876543210',
                $classes->first()->name ?? 'Grade 1',
                $classes->first()->section ?? 'A',
                '1',
                'Robert Doe',
                'robert.doe@email.com',
                '9876543220',
                'Engineer',
                '123 Main Street, City',
                'Mary Doe',
                'mary.doe@email.com',
                '9876543221',
                'Teacher',
                '123 Main Street, City',
                '',
                '',
                '',
                '',
                '',
                ''
            ],
            [
                'STU002',
                'Jane',
                'Smith',
                '2014-08-22',
                'female',
                '2024-04-01',
                'B+',
                '456 Oak Avenue, City',
                '9876543211',
                $classes->count() > 1 ? $classes->get(1)->name : 'Grade 2',
                $classes->count() > 1 ? $classes->get(1)->section : 'B',
                '2',
                'Michael Smith',
                'michael.smith@email.com',
                '9876543222',
                'Doctor',
                '456 Oak Avenue, City',
                '',
                '',
                '',
                '',
                '',
                'Sarah Johnson',
                'sarah.johnson@email.com',
                '9876543223',
                'aunt',
                'Businesswoman',
                '789 Guardian Street, City'
            ],
            [
                'STU003',
                'Alex',
                'Johnson',
                '2016-01-10',
                'other',
                '2024-04-01',
                'O+',
                '789 Pine Road, City',
                '9876543212',
                $classes->count() > 2 ? $classes->get(2)->name : 'Grade 3',
                $classes->count() > 2 ? $classes->get(2)->section : 'A',
                '3',
                '',
                '',
                '',
                '',
                '',
                'Lisa Johnson',
                'lisa.johnson@email.com',
                '9876543224',
                'Nurse',
                '789 Pine Road, City',
                '',
                '',
                '',
                '',
                '',
                ''
            ]
        ];

        // Add sample data
        foreach ($sampleData as $row) {
            $template[] = $row;
        }

        return $template;
    }

    public function downloadTemplate()
    {
        $template = $this->generateTemplate();

        $filename = 'student_import_template_' . date('Y-m-d') . '.csv';
        $tempFile = tempnam(sys_get_temp_dir(), 'student_template');

        $handle = fopen($tempFile, 'w');

        foreach ($template as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }

    private function validateUploadedFile(string $filePath, string $fileName): void
    {
        // Check file extension from filename
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            throw new \Exception('Only CSV files are allowed for student import');
        }

        // Check file size (max 10MB)
        $fileSize = Storage::size($filePath);
        if ($fileSize > 10 * 1024 * 1024) {
            throw new \Exception('File size cannot exceed 10MB');
        }

        // Basic CSV validation - check if we can read the file
        $filePath = Storage::path($filePath);
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new \Exception('Cannot read the uploaded file');
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if (!$headers) {
            throw new \Exception('Invalid CSV file or empty file');
        }

        // Check for required headers (only basic student info is required)
        $requiredHeaders = ['admission_number', 'first_name', 'last_name', 'date_of_birth', 'gender'];
        $missingHeaders = array_diff($requiredHeaders, $headers);

        if (!empty($missingHeaders)) {
            throw new \Exception('Missing required columns: ' . implode(', ', $missingHeaders));
        }

        // Validate parent/guardian fields if provided
        $this->validateParentFields($headers);
    }

    /**
     * Validate parent/guardian fields in CSV headers
     */
    private function validateParentFields(array $headers): void
    {
        // Define parent field groups - if one field is present, email should also be present
        $parentFieldGroups = [
            'father' => ['father_name', 'father_email'],
            'mother' => ['mother_name', 'mother_email'],
            'guardian' => ['guardian_name', 'guardian_email']
        ];

        foreach ($parentFieldGroups as $type => $requiredFields) {
            $nameField = $requiredFields[0];
            $emailField = $requiredFields[1];

            $hasName = in_array($nameField, $headers);
            $hasEmail = in_array($emailField, $headers);

            // If name is provided, email should also be provided
            if ($hasName && !$hasEmail) {
                throw new \Exception("If {$nameField} is provided, {$emailField} must also be provided to create parent account");
            }
        }
    }
}
