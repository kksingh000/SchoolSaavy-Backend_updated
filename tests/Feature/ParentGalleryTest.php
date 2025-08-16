<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\School;
use App\Models\Student;
use App\Models\Parents;
use App\Models\ClassRoom;
use App\Models\GalleryAlbum;
use App\Models\GalleryMedia;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParentGalleryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $parentUser;
    protected $parent;
    protected $student;
    protected $school;
    protected $class;
    protected $galleryModule;

    protected function setUp(): void
    {
        parent::setUp();

        // Create school
        $this->school = School::factory()->create();

        // Create and activate gallery module
        $this->galleryModule = Module::factory()->create([
            'name' => 'Gallery Management',
            'slug' => 'gallery-management',
            'is_active' => true,
        ]);

        // Activate module for school
        DB::table('school_modules')->insert([
            'school_id' => $this->school->id,
            'module_id' => $this->galleryModule->id,
            'status' => 'active',
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create class
        $this->class = ClassRoom::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // Create parent user
        $this->parentUser = User::factory()->create([
            'user_type' => 'parent',
        ]);

        // Create parent profile
        $this->parent = Parents::factory()->create([
            'user_id' => $this->parentUser->id,
        ]);

        // Create student
        $this->student = Student::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // Link parent to student
        DB::table('parent_student')->insert([
            'parent_id' => $this->parent->id,
            'student_id' => $this->student->id,
            'relationship' => 'father',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link student to class
        DB::table('class_student')->insert([
            'class_id' => $this->class->id,
            'student_id' => $this->student->id,
            'roll_number' => '001',
            'enrolled_date' => now(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function parent_can_access_student_gallery()
    {
        $this->actingAs($this->parentUser, 'sanctum');

        // Create a gallery album for the class
        $album = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'title' => 'Class Trip Photos',
            'is_public' => true,
            'status' => 'active',
        ]);

        // Create gallery media
        $media = GalleryMedia::factory()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'title' => 'Group Photo',
            'file_path' => 'https://example.com/photo.jpg',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'student' => [
                        'id',
                        'name',
                        'admission_number',
                        'class',
                    ],
                    'summary' => [
                        'total_items',
                        'class_gallery_items',
                        'assignment_items',
                        'photos_count',
                        'videos_count',
                    ],
                    'items',
                    'pagination',
                ]
            ])
            ->assertJsonPath('status', 'success');
    }

    /** @test */
    public function parent_cannot_access_other_students_gallery()
    {
        $this->actingAs($this->parentUser, 'sanctum');

        // Create another student not related to this parent
        $otherStudent = Student::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $otherStudent->id,
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors', 'Student does not belong to this parent.');
    }

    /** @test */
    public function non_parent_user_cannot_access_gallery()
    {
        $teacherUser = User::factory()->create([
            'user_type' => 'teacher',
        ]);

        $this->actingAs($teacherUser, 'sanctum');

        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Access denied. Only parents can access this resource.');
    }

    /** @test */
    public function gallery_requires_active_module()
    {
        // Deactivate the gallery module
        DB::table('school_modules')
            ->where('school_id', $this->school->id)
            ->where('module_id', $this->galleryModule->id)
            ->update(['status' => 'inactive']);

        $this->actingAs($this->parentUser, 'sanctum');

        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJson([
                'message' => "Module access denied. The 'gallery-management' module is not active for your school."
            ]);
    }

    /** @test */
    public function gallery_includes_assignment_submission_attachments()
    {
        $this->actingAs($this->parentUser, 'sanctum');

        // Create assignment and submission with attachments
        $assignment = Assignment::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
        ]);

        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $this->student->id,
            'status' => 'submitted',
            'attachments' => [
                [
                    'name' => 'homework.jpg',
                    'filename' => 'homework_123.jpg',
                    'mime_type' => 'image/jpeg',
                    'size' => 1024000,
                ]
            ],
        ]);

        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'type' => 'assignments',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.summary.assignment_items', 1);
    }

    /** @test */
    public function gallery_filters_by_media_type()
    {
        $this->actingAs($this->parentUser, 'sanctum');

        // Create gallery album with photo and video
        $album = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'is_public' => true,
            'status' => 'active',
        ]);

        GalleryMedia::factory()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'status' => 'active',
        ]);

        GalleryMedia::factory()->create([
            'album_id' => $album->id,
            'type' => 'video',
            'status' => 'active',
        ]);

        // Test photo filter
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'media_type' => 'photo',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.photos_count', 1)
            ->assertJsonPath('data.summary.videos_count', 0);

        // Test video filter
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'media_type' => 'video',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.photos_count', 0)
            ->assertJsonPath('data.summary.videos_count', 1);
    }

    /** @test */
    public function gallery_validates_request_parameters()
    {
        $this->actingAs($this->parentUser, 'sanctum');

        // Test missing student_id
        $response = $this->postJson('/api/parent/student/gallery', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['student_id']);

        // Test invalid type
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'type' => 'invalid_type',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        // Test invalid media_type
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'media_type' => 'invalid_media_type',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['media_type']);

        // Test invalid per_page range
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'per_page' => 100,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
}
