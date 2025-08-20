<?php

namespace App\Jobs;

use App\Models\StudentImport;
use App\Models\StudentImportError;
use App\Models\Student;
use App\Models\ClassRoom;
use App\Models\Parents;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class ProcessStudentImport implements ShouldQueue
{
    use Queueable;

    protected $studentImport;
    protected $chunkSize = 100; // Process 100 rows at a time

    /**
     * Create a new job instance.
     */
    public function __construct(StudentImport $studentImport)
    {
        $this->studentImport = $studentImport;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting student import processing', ['import_id' => $this->studentImport->id]);

            // Update status to processing
            $this->studentImport->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Read and process CSV file
            $this->processFile();

            // Mark as completed
            $this->studentImport->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('Student import completed successfully', [
                'import_id' => $this->studentImport->id,
                'success_count' => $this->studentImport->success_count,
                'failed_count' => $this->studentImport->failed_count
            ]);
        } catch (\Exception $e) {
            Log::error('Student import failed', [
                'import_id' => $this->studentImport->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->studentImport->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    private function processFile()
    {
        $filePath = Storage::path($this->studentImport->file_path);

        if (!file_exists($filePath)) {
            throw new \Exception('Import file not found: ' . $filePath);
        }

        $file = fopen($filePath, 'r');
        $headers = fgetcsv($file); // Read header row

        // Validate headers
        $expectedHeaders = [
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

        $missingHeaders = array_diff($expectedHeaders, $headers);
        if (!empty($missingHeaders)) {
            throw new \Exception('Missing required headers: ' . implode(', ', $missingHeaders));
        }

        $rowNumber = 1; // Start from 1 (header is row 0)
        $totalRows = 0;
        $successCount = 0;
        $failedCount = 0;

        // Count total rows first
        while (($row = fgetcsv($file)) !== FALSE) {
            $totalRows++;
        }

        // Update total rows
        $this->studentImport->update(['total_rows' => $totalRows]);

        // Reset file pointer
        fclose($file);
        $file = fopen($filePath, 'r');
        fgetcsv($file); // Skip header again

        // Process each row
        while (($row = fgetcsv($file)) !== FALSE) {
            $rowNumber++;

            try {
                $rowData = array_combine($headers, $row);
                $this->processRow($rowData, $rowNumber);
                $successCount++;
            } catch (\Exception $e) {
                $failedCount++;

                // Log the error
                StudentImportError::create([
                    'student_import_id' => $this->studentImport->id,
                    'row_number' => $rowNumber,
                    'row_data' => array_combine($headers, $row),
                    'errors' => ['general' => $e->getMessage()]
                ]);
            }

            // Update progress periodically
            if ($rowNumber % 50 == 0) {
                $this->studentImport->update([
                    'processed_rows' => $rowNumber - 1,
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                ]);
            }
        }

        // Final update
        $this->studentImport->update([
            'processed_rows' => $totalRows,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);

        fclose($file);
    }

    private function processRow(array $rowData, int $rowNumber)
    {
        DB::beginTransaction();
        try {
            // Validate and prepare student data
            $studentData = $this->validateAndPrepareStudentData($rowData);

            // Find or create class
            $class = $this->findOrCreateClass($rowData);

            // Check if student with this admission number already exists for this school
            $existingStudent = Student::where('school_id', $this->studentImport->school_id)
                ->where('admission_number', $studentData['admission_number'])
                ->first();

            if ($existingStudent) {
                throw new \Exception("Student with admission number {$studentData['admission_number']} already exists");
            }

            // Create student
            $student = Student::create($studentData);

            // Assign to class if found
            if ($class) {
                $student->classes()->attach($class->id, [
                    'roll_number' => $rowData['roll_number'] ?? $student->id,
                    'enrolled_date' => now(),
                    'is_active' => true
                ]);
            }

            // Create and assign parents/guardians
            $this->createAndAssignParents($student, $rowData);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            // Create detailed error record
            StudentImportError::create([
                'student_import_id' => $this->studentImport->id,
                'row_number' => $rowNumber,
                'row_data' => $rowData,
                'errors' => ['validation' => $e->getMessage()]
            ]);

            throw $e;
        }
    }

    private function validateAndPrepareStudentData(array $rowData): array
    {
        // Clean and validate data
        $studentData = [
            'school_id' => $this->studentImport->school_id,
            'created_by' => $this->studentImport->user_id,
            'admission_number' => trim($rowData['admission_number']),
            'first_name' => trim($rowData['first_name']),
            'last_name' => trim($rowData['last_name']),
            'date_of_birth' => $this->parseDate($rowData['date_of_birth']),
            'gender' => strtolower(trim($rowData['gender'])),
            'admission_date' => $this->parseDate($rowData['admission_date'] ?? now()->format('Y-m-d')),
            'blood_group' => trim($rowData['blood_group'] ?? ''),
            'address' => trim($rowData['address'] ?? ''),
            'phone' => trim($rowData['phone'] ?? ''),
            'is_active' => true,
        ];

        // Validate required fields
        if (empty($studentData['admission_number'])) {
            throw new \Exception('Admission number is required');
        }

        if (empty($studentData['first_name'])) {
            throw new \Exception('First name is required');
        }

        if (empty($studentData['last_name'])) {
            throw new \Exception('Last name is required');
        }

        if (!in_array($studentData['gender'], ['male', 'female', 'other'])) {
            throw new \Exception('Gender must be male, female, or other');
        }

        return $studentData;
    }

    private function findOrCreateClass(array $rowData): ?ClassRoom
    {
        if (empty($rowData['class_name'])) {
            return null;
        }

        $className = trim($rowData['class_name']);
        $classSection = trim($rowData['class_section'] ?? '');

        // Try to find existing class
        $query = ClassRoom::where('school_id', $this->studentImport->school_id)
            ->where('name', $className)
            ->where('is_active', true);

        if ($classSection) {
            $query->where('section', $classSection);
        }

        return $query->first();
    }

    private function parseDate($dateString): string
    {
        if (empty($dateString)) {
            throw new \Exception('Date is required');
        }

        try {
            return \Carbon\Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            throw new \Exception("Invalid date format: {$dateString}");
        }
    }

    /**
     * Create and assign parents/guardians to student
     */
    private function createAndAssignParents(Student $student, array $rowData): void
    {
        $parentsToCreate = [];

        // Process Father
        if (!empty($rowData['father_name']) && !empty($rowData['father_email'])) {
            $parentsToCreate[] = [
                'type' => 'father',
                'name' => trim($rowData['father_name']),
                'email' => trim($rowData['father_email']),
                'phone' => trim($rowData['father_phone'] ?? ''),
                'occupation' => trim($rowData['father_occupation'] ?? ''),
                'address' => trim($rowData['father_address'] ?? ''),
                'relationship' => 'father',
                'is_primary' => true
            ];
        }

        // Process Mother
        if (!empty($rowData['mother_name']) && !empty($rowData['mother_email'])) {
            $parentsToCreate[] = [
                'type' => 'mother',
                'name' => trim($rowData['mother_name']),
                'email' => trim($rowData['mother_email']),
                'phone' => trim($rowData['mother_phone'] ?? ''),
                'occupation' => trim($rowData['mother_occupation'] ?? ''),
                'address' => trim($rowData['mother_address'] ?? ''),
                'relationship' => 'mother',
                'is_primary' => false
            ];
        }

        // Process Guardian
        if (!empty($rowData['guardian_name']) && !empty($rowData['guardian_email'])) {
            $guardianRelationship = trim($rowData['guardian_relationship'] ?? 'guardian');

            // Validate guardian relationship
            $allowedRelationships = ['uncle', 'aunt', 'grandfather', 'grandmother', 'guardian', 'other'];
            if (!in_array($guardianRelationship, $allowedRelationships)) {
                $guardianRelationship = 'guardian';
            }

            $parentsToCreate[] = [
                'type' => 'guardian',
                'name' => trim($rowData['guardian_name']),
                'email' => trim($rowData['guardian_email']),
                'phone' => trim($rowData['guardian_phone'] ?? ''),
                'occupation' => trim($rowData['guardian_occupation'] ?? ''),
                'address' => trim($rowData['guardian_address'] ?? ''),
                'relationship' => $guardianRelationship,
                'is_primary' => false
            ];
        }

        // Create each parent/guardian
        foreach ($parentsToCreate as $parentData) {
            try {
                $parent = $this->findOrCreateParent($parentData);

                // Check if this parent is already assigned to this student
                $existingRelation = $student->parents()
                    ->where('parent_id', $parent->id)
                    ->first();

                if (!$existingRelation) {
                    // Assign parent to student
                    $student->parents()->attach($parent->id, [
                        'relationship' => $parentData['relationship'],
                        'is_primary' => $parentData['is_primary']
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to create/assign parent', [
                    'student_id' => $student->id,
                    'parent_data' => $parentData,
                    'error' => $e->getMessage()
                ]);
                // Continue processing other parents even if one fails
            }
        }
    }

    /**
     * Find existing parent by email or create new one
     */
    private function findOrCreateParent(array $parentData): Parents
    {
        // Check if user with this email already exists
        $existingUser = User::where('email', $parentData['email'])
            ->where('user_type', 'parent')
            ->first();

        if ($existingUser) {
            // Return existing parent
            $existingParent = Parents::where('user_id', $existingUser->id)->first();
            if ($existingParent) {
                return $existingParent;
            }
        }

        // Create new user for parent
        $user = User::create([
            'name' => $parentData['name'],
            'email' => $parentData['email'],
            'password' => Hash::make('password123'), // Default password
            'user_type' => 'parent',
            'is_active' => true,
            'email_verified_at' => now()
        ]);

        // Determine gender based on relationship
        $gender = $this->determineGender($parentData['relationship']);

        // Create parent record
        $parent = Parents::create([
            'user_id' => $user->id,
            'phone' => $parentData['phone'],
            'gender' => $gender,
            'occupation' => $parentData['occupation'],
            'address' => $parentData['address'],
            'relationship' => $parentData['relationship']
        ]);

        return $parent;
    }

    /**
     * Determine gender based on relationship
     */
    private function determineGender(string $relationship): string
    {
        $maleRelationships = ['father', 'uncle', 'grandfather'];
        $femaleRelationships = ['mother', 'aunt', 'grandmother'];

        if (in_array($relationship, $maleRelationships)) {
            return 'male';
        } elseif (in_array($relationship, $femaleRelationships)) {
            return 'female';
        }

        return 'other'; // For guardian or other
    }
}
