<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\School;
use App\Models\Student;
use App\Models\Parents;
use App\Models\ClassRoom;
use App\Models\GalleryAlbum;
use App\Models\GalleryMedia;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParentGalleryApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $parentUser;
    private Parents $parent;
    private Student $student;
    private School $school;
    private ClassRoom $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestData();
    }

    private function setupTestData(): void
    {
        // Create a school
        $this->school = School::factory()->create([
            'name' => 'Test School',
            'is_active' => true,
        ]);

        // Create gallery management module and activate it for the school
        $galleryModule = Module::factory()->create([
            'name' => 'gallery-management',
            'display_name' => 'Gallery Management',
            'slug' => 'gallery-management',
        ]);

        $this->school->modules()->attach($galleryModule->id, [
            'status' => 'active',
            'activated_at' => now(),
        ]);

        // Create a class
        $this->class = ClassRoom::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Grade 5',
            'section' => 'A',
            'is_active' => true,
        ]);

        // Create a student
        $this->student = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'admission_number' => 'ADM001',
            'is_active' => true,
        ]);

        // Assign student to class
        $this->student->classes()->attach($this->class->id, [
            'enrollment_date' => now(),
            'is_active' => true,
        ]);

        // Create parent user
        $this->parentUser = User::factory()->create([
            'school_id' => $this->school->id,
            'user_type' => 'parent',
            'email' => 'parent@test.com',
        ]);

        // Create parent record
        $this->parent = Parents::factory()->create([
            'school_id' => $this->school->id,
            'user_id' => $this->parentUser->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'parent@test.com',
        ]);

        // Associate parent with student
        $this->parent->students()->attach($this->student->id, [
            'relationship' => 'mother',
            'is_primary' => true,
        ]);
    }

    /** @test */
    public function parent_can_get_student_gallery_content(): void
    {
        // Create class gallery album and media
        $classAlbum = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'title' => 'Science Fair 2025',
            'description' => 'Annual science exhibition',
            'status' => 'active',
            'is_public' => true,
        ]);

        $classMedia = GalleryMedia::factory()->create([
            'album_id' => $classAlbum->id,
            'title' => 'Science Project Display',
            'description' => 'Students showcasing projects',
            'type' => 'photo',
            'file_path' => '/storage/gallery/science-fair.jpg',
            'file_size' => 1248576,
            'status' => 'active',
        ]);

        // Create school-wide gallery album and media
        $schoolAlbum = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => null, // School-wide
            'title' => 'School Events',
            'description' => 'General school activities',
            'status' => 'active',
            'is_public' => true,
        ]);

        $schoolMedia = GalleryMedia::factory()->create([
            'album_id' => $schoolAlbum->id,
            'title' => 'Morning Assembly',
            'description' => 'Daily morning assembly',
            'type' => 'video',
            'file_path' => '/storage/gallery/assembly.mp4',
            'file_size' => 5248576,
            'status' => 'active',
        ]);

        // Authenticate as parent
        Sanctum::actingAs($this->parentUser);

        // Make API request
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'per_page' => 10,
        ]);

        // Assert response structure and data
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'student' => [
                        'id',
                        'name',
                        'admission_number',
                        'class' => [
                            'id',
                            'name',
                            'section',
                        ],
                    ],
                    'summary' => [
                        'total_items',
                        'photos_count',
                        'videos_count',
                        'documents_count',
                    ],
                    'items' => [
                        '*' => [
                            'id',
                            'type',
                            'media_type',
                            'title',
                            'description',
                            'file_url',
                            'thumbnail_url',
                            'file_size',
                            'file_size_human',
                            'created_at',
                            'album',
                            'source',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Student gallery retrieved successfully.',
                'data' => [
                    'student' => [
                        'id' => $this->student->id,
                        'name' => 'John Doe',
                        'admission_number' => 'ADM001',
                    ],
                    'summary' => [
                        'total_items' => 2,
                        'photos_count' => 1,
                        'videos_count' => 1,
                    ],
                ],
            ]);

        // Verify we have both class and school gallery items
        $items = $response->json('data.items');
        $this->assertCount(2, $items);

        $types = collect($items)->pluck('type')->toArray();
        $this->assertContains('class_gallery', $types);
        $this->assertContains('school_gallery', $types);
    }

    /** @test */
    public function parent_can_filter_gallery_by_media_type(): void
    {
        // Create mixed media types
        $album = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        // Create photo
        GalleryMedia::factory()->create([
            'album_id' => $album->id,
            'type' => 'photo',
            'status' => 'active',
        ]);

        // Create video
        GalleryMedia::factory()->create([
            'album_id' => $album->id,
            'type' => 'video',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->parentUser);

        // Test photo filter
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'media_type' => 'photo',
        ]);

        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('photo', $items[0]['media_type']);
    }

    /** @test */
    public function parent_cannot_access_another_students_gallery(): void
    {
        // Create another student not associated with this parent
        $anotherStudent = Student::factory()->create([
            'school_id' => $this->school->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        Sanctum::actingAs($this->parentUser);

        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $anotherStudent->id,
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Failed to retrieve student gallery.',
            ]);
    }

    /** @test */
    public function gallery_api_requires_gallery_management_module(): void
    {
        // Deactivate gallery management module
        DB::table('school_modules')
            ->where('school_id', $this->school->id)
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('modules')
                    ->where('modules.name', 'gallery-management')
                    ->whereColumn('modules.id', 'school_modules.module_id');
            })
            ->update(['status' => 'inactive']);

        Sanctum::actingAs($this->parentUser);

        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Module access denied.',
            ]);
    }

    /** @test */
    public function gallery_api_validates_request_parameters(): void
    {
        Sanctum::actingAs($this->parentUser);

        // Test missing student_id
        $response = $this->postJson('/api/parent/student/gallery', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('student_id');

        // Test invalid media_type
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'media_type' => 'invalid_type',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('media_type');

        // Test invalid per_page
        $response = $this->postJson('/api/parent/student/gallery', [
            'student_id' => $this->student->id,
            'per_page' => 100, // Above max limit
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }
}
