<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\Teacher;
use Laravel\Sanctum\Sanctum;

class ClassControllerTest extends TestCase
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

    public function test_can_list_classes()
    {
        // Create some classes
        ClassRoom::factory()->count(3)->create(['school_id' => $this->school->id]);

        $response = $this->getJson('/api/classes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'section',
                            'grade_level',
                            'capacity',
                            'is_active'
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_show_class_details()
    {
        $teacher = Teacher::factory()->create(['school_id' => $this->school->id]);
        $class = ClassRoom::factory()->create([
            'school_id' => $this->school->id,
            'class_teacher_id' => $teacher->id
        ]);

        $response = $this->getJson("/api/classes/{$class->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'section',
                    'grade_level',
                    'capacity',
                    'class_teacher',
                    'active_students'
                ]
            ]);
    }

    public function test_can_create_class()
    {
        $teacher = Teacher::factory()->create(['school_id' => $this->school->id]);

        $classData = [
            'name' => 'Grade 1',
            'section' => 'A',
            'grade_level' => 1,
            'capacity' => 30,
            'class_teacher_id' => $teacher->id,
            'description' => 'First grade class',
            'is_active' => true
        ];

        $response = $this->postJson('/api/classes', $classData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'section',
                    'grade_level',
                    'capacity'
                ]
            ]);

        $this->assertDatabaseHas('classes', [
            'name' => 'Grade 1',
            'section' => 'A',
            'school_id' => $this->school->id
        ]);
    }

    public function test_can_update_class()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        $updateData = [
            'name' => 'Updated Grade 1',
            'section' => 'B',
            'capacity' => 35
        ];

        $response = $this->putJson("/api/classes/{$class->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'name' => 'Updated Grade 1',
            'section' => 'B',
            'capacity' => 35
        ]);
    }

    public function test_can_delete_class()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        $response = $this->deleteJson("/api/classes/{$class->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('classes', ['id' => $class->id]);
    }

    public function test_can_assign_students_to_class()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $students = Student::factory()->count(3)->create(['school_id' => $this->school->id]);

        $assignData = [
            'students' => [
                ['student_id' => $students[0]->id, 'roll_number' => '001'],
                ['student_id' => $students[1]->id, 'roll_number' => '002'],
                ['student_id' => $students[2]->id, 'roll_number' => '003'],
            ]
        ];

        $response = $this->postJson("/api/classes/{$class->id}/assign-students", $assignData);

        $response->assertStatus(200);

        // Check if students are assigned to class
        $this->assertDatabaseHas('class_student', [
            'class_id' => $class->id,
            'student_id' => $students[0]->id,
            'roll_number' => '001'
        ]);
    }

    public function test_can_get_class_students()
    {
        $class = ClassRoom::factory()->create(['school_id' => $this->school->id]);
        $student = Student::factory()->create(['school_id' => $this->school->id]);

        // Assign student to class
        $class->activeStudents()->attach($student->id, [
            'roll_number' => '001',
            'enrolled_date' => now(),
            'is_active' => true
        ]);

        $response = $this->getJson("/api/classes/{$class->id}/students");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'pivot' => [
                            'roll_number',
                            'enrolled_date',
                            'is_active'
                        ]
                    ]
                ]
            ]);
    }

    public function test_validation_errors_when_creating_class()
    {
        $response = $this->postJson('/api/classes', [
            'name' => '', // Required field empty
            'grade_level' => 'invalid', // Should be integer
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'grade_level']);
    }

    public function test_cannot_access_other_school_classes()
    {
        // Create another school and class
        $otherSchool = School::factory()->create();
        $otherClass = ClassRoom::factory()->create(['school_id' => $otherSchool->id]);

        $response = $this->getJson("/api/classes/{$otherClass->id}");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_access_denied()
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/classes');
        $response->assertStatus(401);
    }
}
