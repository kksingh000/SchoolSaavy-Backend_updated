<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\School;
use App\Models\ClassRoom;
use App\Models\Event;
use App\Models\GalleryAlbum;
use App\Models\GalleryMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $school;
    protected $class;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->school = School::factory()->create();
        $this->user = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'teacher'
        ]);
        $this->class = ClassRoom::factory()->create([
            'school_id' => $this->school->id
        ]);
        $this->event = Event::factory()->create([
            'school_id' => $this->school->id
        ]);
    }

    /** @test */
    public function authenticated_user_can_create_gallery_album_with_media()
    {
        $this->actingAs($this->user);

        $photo = UploadedFile::fake()->image('test-photo.jpg', 800, 600)->size(1000);
        $video = UploadedFile::fake()->create('test-video.mp4', 5000, 'video/mp4');

        $response = $this->postJson('/api/gallery', [
            'title' => 'Independence Day Celebration',
            'description' => 'Photos and videos from our Independence Day event',
            'class_id' => $this->class->id,
            'event_id' => $this->event->id,
            'event_date' => '2025-08-15',
            'is_public' => true,
            'media_files' => [$photo, $video]
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Gallery album created successfully'
            ]);

        $this->assertDatabaseHas('gallery_albums', [
            'title' => 'Independence Day Celebration',
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'event_id' => $this->event->id,
            'created_by' => $this->user->id,
            'media_count' => 2
        ]);

        // Check that media files were created
        $album = GalleryAlbum::where('title', 'Independence Day Celebration')->first();
        $this->assertEquals(2, $album->media()->count());

        // Check media types
        $this->assertTrue($album->media()->where('type', 'photo')->exists());
        $this->assertTrue($album->media()->where('type', 'video')->exists());
    }

    /** @test */
    public function user_can_view_gallery_albums_list()
    {
        $this->actingAs($this->user);

        $albums = GalleryAlbum::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id
        ]);

        $response = $this->getJson('/api/gallery');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'class',
                            'event',
                            'creator',
                            'media_count',
                            'cover_image',
                            'status'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function user_can_filter_albums_by_class()
    {
        $this->actingAs($this->user);

        $class2 = ClassRoom::factory()->create(['school_id' => $this->school->id]);

        GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'title' => 'Class 1 Album'
        ]);

        GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $class2->id,
            'title' => 'Class 2 Album'
        ]);

        $response = $this->getJson('/api/gallery?class_id=' . $this->class->id);

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(1, count($responseData['data']['data']));
        $this->assertEquals('Class 1 Album', $responseData['data']['data'][0]['title']);
    }

    /** @test */
    public function user_can_view_specific_album_with_media()
    {
        $this->actingAs($this->user);

        $album = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id
        ]);

        $media = GalleryMedia::factory()->count(3)->create([
            'album_id' => $album->id
        ]);

        $response = $this->getJson("/api/gallery/{$album->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'media' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'file_path',
                            'thumbnail_path',
                            'file_size'
                        ]
                    ]
                ]
            ]);

        $this->assertEquals(3, count($response->json('data.media')));
    }

    /** @test */
    public function user_can_update_album_details()
    {
        $this->actingAs($this->user);

        $album = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'title' => 'Original Title'
        ]);

        $response = $this->putJson("/api/gallery/{$album->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'event_date' => '2025-09-15',
            'is_public' => false,
            'status' => 'inactive'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gallery album updated successfully'
            ]);

        $this->assertDatabaseHas('gallery_albums', [
            'id' => $album->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'is_public' => false,
            'status' => 'inactive'
        ]);
    }

    /** @test */
    public function user_can_add_media_to_existing_album()
    {
        $this->actingAs($this->user);

        $album = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'media_count' => 2
        ]);

        $newPhoto = UploadedFile::fake()->image('new-photo.jpg');

        $response = $this->postJson("/api/gallery/{$album->id}/media", [
            'media_files' => [$newPhoto]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media added successfully'
            ]);

        $album->refresh();
        $this->assertEquals(3, $album->media_count);
    }

    /** @test */
    public function user_can_delete_media_from_album()
    {
        $this->actingAs($this->user);

        $album = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'media_count' => 2
        ]);

        $media = GalleryMedia::factory()->create([
            'album_id' => $album->id
        ]);

        $response = $this->deleteJson("/api/gallery/{$album->id}/media/{$media->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media deleted successfully'
            ]);

        $this->assertDatabaseMissing('gallery_media', [
            'id' => $media->id
        ]);

        $album->refresh();
        $this->assertEquals(1, $album->media_count);
    }

    /** @test */
    public function user_can_delete_entire_album()
    {
        $this->actingAs($this->user);

        $album = GalleryAlbum::factory()->create([
            'school_id' => $this->school->id
        ]);

        $media = GalleryMedia::factory()->count(2)->create([
            'album_id' => $album->id
        ]);

        $response = $this->deleteJson("/api/gallery/{$album->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gallery album deleted successfully'
            ]);

        $this->assertDatabaseMissing('gallery_albums', [
            'id' => $album->id
        ]);

        // Check that associated media is also deleted
        foreach ($media as $mediaItem) {
            $this->assertDatabaseMissing('gallery_media', [
                'id' => $mediaItem->id
            ]);
        }
    }

    /** @test */
    public function user_can_get_classes_for_dropdown()
    {
        $this->actingAs($this->user);

        ClassRoom::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'is_active' => true
        ]);

        $response = $this->getJson('/api/gallery/classes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'grade_level',
                        'section'
                    ]
                ]
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    /** @test */
    public function user_can_get_events_for_dropdown()
    {
        $this->actingAs($this->user);

        Event::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'is_published' => true
        ]);

        $response = $this->getJson('/api/gallery/events');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'event_date',
                        'type'
                    ]
                ]
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    /** @test */
    public function validation_fails_for_invalid_media_files()
    {
        $this->actingAs($this->user);

        // Try uploading a text file (not allowed)
        $invalidFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->postJson('/api/gallery', [
            'title' => 'Test Album',
            'class_id' => $this->class->id,
            'event_date' => '2025-08-15',
            'media_files' => [$invalidFile]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['media_files.0']);
    }

    /** @test */
    public function validation_fails_for_oversized_files()
    {
        $this->actingAs($this->user);

        // Try uploading a file larger than 20MB
        $oversizedFile = UploadedFile::fake()->create('large-video.mp4', 25000, 'video/mp4'); // 25MB

        $response = $this->postJson('/api/gallery', [
            'title' => 'Test Album',
            'class_id' => $this->class->id,
            'event_date' => '2025-08-15',
            'media_files' => [$oversizedFile]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['media_files.0']);
    }

    /** @test */
    public function user_cannot_access_other_school_albums()
    {
        $this->actingAs($this->user);

        $otherSchool = School::factory()->create();
        $otherAlbum = GalleryAlbum::factory()->create([
            'school_id' => $otherSchool->id
        ]);

        $response = $this->getJson("/api/gallery/{$otherAlbum->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function user_can_search_albums_by_title()
    {
        $this->actingAs($this->user);

        GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'title' => 'Sports Day Event'
        ]);

        GalleryAlbum::factory()->create([
            'school_id' => $this->school->id,
            'title' => 'Science Fair'
        ]);

        $response = $this->getJson('/api/gallery?search=Sports');

        $response->assertStatus(200);
        $responseData = $response->json();

        $this->assertEquals(1, count($responseData['data']['data']));
        $this->assertStringContainsString('Sports', $responseData['data']['data'][0]['title']);
    }
}
