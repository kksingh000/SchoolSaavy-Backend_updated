<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('camera_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('camera_id')->constrained('school_cameras')->onDelete('cascade');
            $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->boolean('access_granted')->default(false);
            $table->timestamp('access_start_time')->nullable();
            $table->timestamp('access_end_time')->nullable();
            $table->enum('permission_type', ['permanent', 'temporary', 'scheduled'])->default('permanent');
            $table->json('schedule_settings')->nullable(); // For scheduled access (days, hours)
            $table->text('justification')->nullable();
            $table->enum('request_status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['school_id', 'parent_id']);
            $table->index(['camera_id', 'access_granted']);
            $table->index(['student_id', 'access_granted']);
            $table->index(['request_status']);
            
            // Unique constraint to prevent duplicate permissions
            $table->unique(['camera_id', 'parent_id', 'student_id'], 'unique_camera_parent_student');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camera_permissions');
    }
};