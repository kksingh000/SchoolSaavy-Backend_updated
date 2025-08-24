<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\AcademicYear;
use App\Models\PromotionBatch;
use App\Jobs\ProcessBulkPromotionEvaluation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class PromotionQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_evaluation_dispatches_queue_job()
    {
        Queue::fake();

        // Create test data
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);

        $admin = User::factory()->create([
            'user_type' => 'admin',
            'school_admin' => ['school_id' => $school->id]
        ]);

        // Act as admin and make request
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/promotions/bulk-evaluate', [
                'academic_year_id' => $academicYear->id
            ]);

        // Assert immediate response with queued batch
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'batch_name',
                    'status',
                    'created_at'
                ]
            ]);

        // Assert batch was created with queued status
        $this->assertDatabaseHas('promotion_batches', [
            'academic_year_id' => $academicYear->id,
            'status' => 'queued',
            'school_id' => $school->id
        ]);

        // Assert queue job was dispatched
        Queue::assertPushed(ProcessBulkPromotionEvaluation::class, function ($job) use ($academicYear) {
            return $job->academicYearId === $academicYear->id;
        });
    }

    public function test_batch_progress_api_returns_real_time_data()
    {
        // Create test data
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);

        $batch = PromotionBatch::factory()->create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'status' => 'processing',
            'total_students' => 100,
            'processed_students' => 75,
            'promoted_students' => 50,
            'failed_students' => 15,
            'pending_students' => 10
        ]);

        $admin = User::factory()->create([
            'user_type' => 'admin',
            'school_admin' => ['school_id' => $school->id]
        ]);

        // Test progress API
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/promotions/batches/{$batch->id}/progress");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $batch->id,
                    'status' => 'processing',
                    'total_students' => 100,
                    'processed_students' => 75,
                    'promoted_students' => 50,
                    'failed_students' => 15,
                    'pending_students' => 10,
                    'progress_percentage' => 75.0,
                    'promotion_rate' => 66.67
                ]
            ]);
    }
}
