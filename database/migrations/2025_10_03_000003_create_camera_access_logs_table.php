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
        Schema::create('camera_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camera_id')->constrained('school_cameras')->onDelete('cascade');
            $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('student_id')->nullable()->constrained('students')->onDelete('set null');
            $table->timestamp('access_start_time');
            $table->timestamp('access_end_time')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->ipAddress('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, tablet, desktop
            $table->enum('access_result', ['success', 'denied', 'error', 'timeout'])->default('success');
            $table->text('error_message')->nullable();
            $table->json('session_metadata')->nullable(); // Additional session info
            $table->timestamps();

            // Indexes for analytics and reporting
            $table->index(['camera_id', 'access_start_time']);
            $table->index(['parent_id', 'access_start_time']);
            $table->index(['student_id', 'access_start_time']);
            $table->index(['access_result']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camera_access_logs');
    }
};