<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Models\ClassRoom;
use App\Models\Attendance;
use App\Models\StudentFee;
use App\Models\FeeStructure;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $school;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test school
        $this->school = School::factory()->create([
            'name' => 'Test School',
            'email' => 'test@school.com',
            'is_active' => true
        ]);

        // Create authenticated user
        $this->user = User::factory()->create(['user_type' => 'school_admin']);
        SchoolAdmin::factory()->create([
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_can_list_students()
    {
        Student::factory()->count(5)->create(['school_id' => $this->school->id]);

        $response = $this->getJson('/api/students');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ])
            ->assertJson([
                'status' => 'success'
            ]);
    }

    public function test_can_show_student_details()
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $response = $this->getJson("/api/students/{$student->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'admission_number',
                    'date_of_birth',
                    'phone',
                    'address',
                    'is_active'
                ]
            ]);
    }

    public function test_can_create_student()
    {
        $parent = \App\Models\Parents::factory()->create(['school_id' => $this->school->id]);
        
        $studentData = [
            'school_id' => $this->school->id,
            'admission_number' => 'ADM001',
            'roll_number' => 'ROLL001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'admission_date' => '2024-01-15',
            'blood_group' => 'A+',
            'address' => '123 Main St',
            'phone' => '1234567890',
            'parent_id' => $parent->id,
            'relationship' => 'father',
            'is_primary' => true
        ];

        $response = $this->postJson('/api/students', $studentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'admission_number'
                ]
            ]);

        $this->assertDatabaseHas('students', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'school_id' => $this->school->id
        ]);
    }

    public function test_can_update_student()
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '9999999999'
        ];

        $response = $this->putJson("/api/students/{$student->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'name' => 'Updated Name',
            'phone' => '9999999999'
        ]);
    }

    public function test_can_delete_student()
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        $response = $this->deleteJson("/api/students/{$student->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }

    public function test_can_get_student_attendance_report()
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        // Create attendance records
        Attendance::factory()->count(10)->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'school_id' => $this->school->id,
            'status' => 'present',
            'date' => Carbon::now()->subDays(rand(1, 30))
        ]);

        Attendance::factory()->count(2)->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'school_id' => $this->school->id,
            'status' => 'absent',
            'date' => Carbon::now()->subDays(rand(1, 30))
        ]);

        $response = $this->getJson("/api/students/{$student->id}/attendance");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'student',
                    'total_days',
                    'present_days',
                    'absent_days',
                    'attendance_percentage',
                    'recent_attendance'
                ]
            ]);
    }

    public function test_can_get_student_fee_status()
    {
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        // Create fee structure
        $feeStructure = FeeStructure::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Monthly Fee',
            'amount' => 5000,
            'frequency' => 'monthly'
        ]);

        // Create student fee
        StudentFee::factory()->create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'amount_due' => 5000,
            'amount_paid' => 3000,
            'due_date' => Carbon::now()->addDays(30),
            'status' => 'partial'
        ]);

        $response = $this->getJson("/api/students/{$student->id}/fees");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'student',
                    'total_due',
                    'total_paid',
                    'balance',
                    'overdue_amount',
                    'fees' => [
                        '*' => [
                            'id',
                            'fee_structure',
                            'amount_due',
                            'amount_paid',
                            'status'
                        ]
                    ]
                ]
            ]);
    }

    public function test_validation_errors_when_creating_student()
    {
        $response = $this->postJson('/api/students', [
            'first_name' => '', // Required field empty
            'last_name' => '', // Required field empty
            'date_of_birth' => 'invalid-date' // Invalid date format
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'date_of_birth']);
    }

    public function test_cannot_create_student_with_duplicate_admission_number()
    {
        $existingStudent = Student::factory()->create([
            'school_id' => $this->school->id,
            'admission_number' => 'ADM001'
        ]);

        $response = $this->postJson('/api/students', [
            'name' => 'New Student',
            'email' => 'new@example.com',
            'admission_number' => 'ADM001', // Duplicate admission number
            'date_of_birth' => '2010-01-01'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['admission_number']);
    }

    public function test_cannot_access_other_school_students()
    {
        // Create another school and student
        $otherSchool = School::factory()->create();
        $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $response = $this->getJson("/api/students/{$otherStudent->id}");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_access_denied()
    {
        // Create a fresh test instance without authentication
        $response = $this->withHeaders([])->getJson('/api/students');
        $response->assertStatus(401);
    }
}
