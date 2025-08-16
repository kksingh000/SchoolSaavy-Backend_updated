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

class ParentGalleryAlbumsApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $parentUser;
    private Parents $parent;
    private Student $student;
    private School $school;
    private ClassRoom $class;
    private GalleryAlbum $classAlbum;
    private GalleryAlbum $schoolAlbum;

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

        // Create class album
        $this->classAlbum = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'title' => 'Science Fair 2025',
            'description' => 'Annual science exhibition',
            'status' => 'active',
            'is_public' => true,
            'media_count' => 0,
        ]);

        // Create school-wide album
        $this->schoolAlbum = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => null, // School-wide
            'title' => 'Cultural Fest',
            'description' => 'Annual cultural festival',
            'status' => 'active',
            'is_public' => true,
            'media_count' => 0,
        ]);
    }

    /** @test */
    public function parent_can_get_student_gallery_albums(): void
    {
        // Add some media to albums for counts
        GalleryMedia::factory()->count(3)->create([
            'album_id' => $this->classAlbum->id,
            'type' => 'photo',
            'status' => 'active',
        ]);

        GalleryMedia::factory()->count(2)->create([
            'album_id' => $this->schoolAlbum->id,
            'type' => 'video',
            'status' => 'active',
        ]);

        // Update album media counts
        $this->classAlbum->update(['media_count' => 3]);
        $this->schoolAlbum->update(['media_count' => 2]);

        Sanctum::actingAs($this->parentUser);

        $response = $this->postJson('/api/parent/student/gallery/albums', [
            'student_id' => $this->student->id,
            'per_page' => 10,
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
                        'class' => [
                            'id',
                            'name',
                            'section',
                        ],
                    ],
                    'albums' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'description',
                                'album_type',
                                'total_media_count',
                                'photos_count',
                                'videos_count',
                                'documents_count',
                                'thumbnails',
                            ],
                        ],
                        'pagination' => [
                            'current_page',
                            'per_page',
                            'total',
                            'last_page',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Student gallery albums retrieved successfully.',
                'data' => [
                    'student' => [
                        'id' => $this->student->id,
                        'name' => 'John Doe',
                    ],
                ],
            ]);

        $albums = $response->json('data.albums.data');
        $this->assertCount(2, $albums);

        // Check we have both class and school albums
        $albumTypes = collect($albums)->pluck('album_type')->toArray();
        $this->assertContains('class', $albumTypes);
        $this->assertContains('school', $albumTypes);
    }

    /** @test */
    public function parent_can_get_album_media(): void
    {
        // Create media items in the class album
        $media1 = GalleryMedia::factory()->create([
            'album_id' => $this->classAlbum->id,
            'type' => 'photo',
            'title' => 'Science Project 1',
            'status' => 'active',
            'is_featured' => true,
        ]);

        $media2 = GalleryMedia::factory()->create([
            'album_id' => $this->classAlbum->id,
            'type' => 'video',
            'title' => 'Presentation Video',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->parentUser);

        $response = $this->postJson('/api/parent/student/gallery/album/media', [
            'student_id' => $this->student->id,
            'album_id' => $this->classAlbum->id,
            'per_page' => 20,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'album' => [
                        'id',
                        'title',
                        'description',
                        'album_type',
                        'total_media_count',
                    ],
                    'media' => [
                        'data' => [
                            '*' => [
                                'id',
                                'type',
                                'title',
                                'description',
                                'file_url',
                                'thumbnail_url',
                                'file_size',
                                'is_featured',
                                'created_at',
                            ],
                        ],
                        'pagination' => [
                            'current_page',
                            'per_page',
                            'total',
                        ],
                    ],
                    'summary' => [
                        'total_items',
                        'photos_count',
                        'videos_count',
                        'documents_count',
                    ],
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'album' => [
                        'id' => $this->classAlbum->id,
                        'title' => 'Science Fair 2025',
                        'album_type' => 'class',
                    ],
                ],
            ]);

        $media = $response->json('data.media.data');
        $this->assertCount(2, $media);

        // Check that featured media comes first
        $this->assertTrue($media[0]['is_featured']);
        $this->assertEquals('Science Project 1', $media[0]['title']);
    }

    /** @test */
    public function parent_can_filter_album_media_by_type(): void
    {
        // Create mixed media types
        GalleryMedia::factory()->create([
            'album_id' => $this->classAlbum->id,
            'type' => 'photo',
            'status' => 'active',
        ]);

        GalleryMedia::factory()->create([
            'album_id' => $this->classAlbum->id,
            'type' => 'video',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->parentUser);

        // Test photo filter
        $response = $this->postJson('/api/parent/student/gallery/album/media', [
            'student_id' => $this->student->id,
            'album_id' => $this->classAlbum->id,
            'media_type' => 'photo',
        ]);

        $response->assertStatus(200);
        $media = $response->json('data.media.data');
        $this->assertCount(1, $media);
        $this->assertEquals('photo', $media[0]['type']);
    }

    /** @test */
    public function parent_cannot_access_another_students_albums(): void
    {
        // Create another student not associated with this parent
        $anotherStudent = Student::factory()->create([
            'school_id' => $this->school->id,
        ]);

        Sanctum::actingAs($this->parentUser);

        $response = $this->postJson('/api/parent/student/gallery/albums', [
            'student_id' => $anotherStudent->id,
        ]);

        $response->assertStatus(500)
            ->assertJsonFragment([
                'message' => 'Failed to retrieve student gallery albums.',
            ]);
    }

    /** @test */
    public function parent_cannot_access_album_not_available_to_student(): void
    {
        // Create another class and album
        $anotherClass = ClassRoom::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $anotherAlbum = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $anotherClass->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        Sanctum::actingAs($this->parentUser);

        $response = $this->postJson('/api/parent/student/gallery/album/media', [
            'student_id' => $this->student->id,
            'album_id' => $anotherAlbum->id,
        ]);

        $response->assertStatus(500)
            ->assertJsonFragment([
                'message' => 'Failed to retrieve album media.',
            ]);
    }

    /** @test */
    public function albums_api_requires_gallery_management_module(): void
    {
        // Deactivate gallery management module
        DB::table('school_modules')
            ->where('school_id', $this->school->id)
            ->update(['status' => 'inactive']);

        Sanctum::actingAs($this->parentUser);

        $response = $this->postJson('/api/parent/student/gallery/albums', [
            'student_id' => $this->student->id,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Module access denied.',
            ]);
    }

    /** @test */
    public function apis_validate_request_parameters(): void
    {
        Sanctum::actingAs($this->parentUser);

        // Test missing student_id in albums API
        $response = $this->postJson('/api/parent/student/gallery/albums', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('student_id');

        // Test missing album_id in media API
        $response = $this->postJson('/api/parent/student/gallery/album/media', [
            'student_id' => $this->student->id,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('album_id');

        // Test invalid media_type
        $response = $this->postJson('/api/parent/student/gallery/album/media', [
            'student_id' => $this->student->id,
            'album_id' => $this->classAlbum->id,
            'media_type' => 'invalid_type',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('media_type');
    }

    /** @test */
    public function albums_api_shows_up_to_3_thumbnails_per_album(): void
    {
        // Create 5 photos (should only get 3 as thumbnails)
        GalleryMedia::factory()->count(5)->create([
            'album_id' => $this->classAlbum->id,
            'type' => 'photo',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->parentUser);

        $response = $this->postJson('/api/parent/student/gallery/albums', [
            'student_id' => $this->student->id,
        ]);

        $response->assertStatus(200);

        $albums = $response->json('data.albums.data');
        $classAlbum = collect($albums)->firstWhere('album_type', 'class');

        $this->assertNotNull($classAlbum);
        $this->assertCount(3, $classAlbum['thumbnails']); // Max 3 thumbnails
    }
}
