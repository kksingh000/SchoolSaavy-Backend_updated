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
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class AttendanceControllerTest extends TestCase
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

    public function test_can_list_attendance_records()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        // Create attendance records
        Attendance::factory()->count(5)->create([
            'school_id' => $this->school->id,
            'class_id' => $class->id,
            'student_id' => $student->id
        ]);

        $response = $this->getJson('/api/attendance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'student',
                            'class',
                            'date',
                            'status',
                            'remarks'
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_mark_bulk_attendance()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $students = Student::factory()->count(3)->create(['school_id' => $this->school->id]);

        $attendanceData = [
            'class_id' => $class->id,
            'date' => Carbon::today()->toDateString(),
            'attendance' => [
                [
                    'student_id' => $students[0]->id,
                    'status' => 'present'
                ],
                [
                    'student_id' => $students[1]->id,
                    'status' => 'absent',
                    'remarks' => 'Sick leave'
                ],
                [
                    'student_id' => $students[2]->id,
                    'status' => 'late'
                ]
            ]
        ];

        $response = $this->postJson('/api/attendance/bulk', $attendanceData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Bulk attendance marked successfully'
            ]);

        // Check if attendance records are created
        $this->assertDatabaseHas('attendances', [
            'student_id' => $students[0]->id,
            'class_id' => $class->id,
            'status' => 'present',
            'date' => Carbon::today()->toDateString()
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $students[1]->id,
            'class_id' => $class->id,
            'status' => 'absent',
            'remarks' => 'Sick leave'
        ]);
    }

    public function test_can_get_class_attendance_report()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $students = Student::factory()->count(3)->create(['school_id' => $this->school->id]);

        // Create varied attendance records
        foreach ($students as $student) {
            // Present records
            Attendance::factory()->count(8)->create([
                'school_id' => $this->school->id,
                'class_id' => $class->id,
                'student_id' => $student->id,
                'status' => 'present',
                'date' => Carbon::now()->subDays(rand(1, 30))
            ]);

            // Absent records
            Attendance::factory()->count(2)->create([
                'school_id' => $this->school->id,
                'class_id' => $class->id,
                'student_id' => $student->id,
                'status' => 'absent',
                'date' => Carbon::now()->subDays(rand(1, 30))
            ]);
        }

        $response = $this->getJson("/api/attendance/class/{$class->id}/report");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'class',
                    'total_students',
                    'total_days',
                    'overall_attendance_percentage',
                    'students' => [
                        '*' => [
                            'student',
                            'total_days',
                            'present_days',
                            'absent_days',
                            'attendance_percentage'
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_get_student_attendance_report()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        // Create attendance records
        Attendance::factory()->count(15)->create([
            'school_id' => $this->school->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'present',
            'date' => Carbon::now()->subDays(rand(1, 30))
        ]);

        Attendance::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'absent',
            'date' => Carbon::now()->subDays(rand(1, 30))
        ]);

        $response = $this->getJson("/api/attendance/student/{$student->id}/report");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'student',
                    'total_days',
                    'present_days',
                    'absent_days',
                    'late_days',
                    'attendance_percentage',
                    'monthly_breakdown' => [
                        '*' => [
                            'month',
                            'total_days',
                            'present_days',
                            'attendance_percentage'
                        ]
                    ],
                    'recent_attendance' => [
                        '*' => [
                            'date',
                            'status',
                            'remarks'
                        ]
                    ]
                ]
            ]);
    }

    public function test_bulk_attendance_validation_errors()
    {
        $response = $this->postJson('/api/attendance/bulk', [
            'class_id' => '', // Required field empty
            'date' => 'invalid-date', // Invalid date format
            'attendance' => [] // Empty attendance array
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['class_id', 'date', 'attendance']);
    }

    public function test_bulk_attendance_with_invalid_student_data()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        $response = $this->postJson('/api/attendance/bulk', [
            'class_id' => $class->id,
            'date' => Carbon::today()->toDateString(),
            'attendance' => [
                [
                    'student_id' => 999, // Non-existent student
                    'status' => 'present'
                ],
                [
                    'student_id' => '', // Empty student ID
                    'status' => 'invalid_status' // Invalid status
                ]
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'attendance.0.student_id',
                'attendance.1.student_id',
                'attendance.1.status'
            ]);
    }

    public function test_cannot_mark_duplicate_attendance_for_same_date()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);
        $date = Carbon::today()->toDateString();

        // Create existing attendance record
        Attendance::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'date' => $date,
            'status' => 'present'
        ]);

        $attendanceData = [
            'class_id' => $class->id,
            'date' => $date,
            'attendance' => [
                [
                    'student_id' => $student->id,
                    'status' => 'absent'
                ]
            ]
        ];

        $response = $this->postJson('/api/attendance/bulk', $attendanceData);

        // Should either update existing or return validation error
        // Based on business logic, let's assume it should update
        $response->assertStatus(200);

        // Check if record was updated
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'date' => $date,
            'status' => 'absent'
        ]);
    }

    public function test_cannot_access_other_school_attendance_data()
    {
        // Create another school's data
        $otherSchool = School::factory()->create();
        $otherClass = ClassRoom::factory()->create(['school_id' => $otherSchool->id]);
        $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id]);

        $response = $this->getJson("/api/attendance/class/{$otherClass->id}/report");
        $response->assertStatus(404);

        $response = $this->getJson("/api/attendance/student/{$otherStudent->id}/report");
        $response->assertStatus(404);
    }

    public function test_unauthenticated_access_denied()
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/attendance');
        $response->assertStatus(401);

        $response = $this->postJson('/api/attendance/bulk', []);
        $response->assertStatus(401);
    }
}
