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
        Schema::create('school_cameras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->nullable()->constrained('classes')->onDelete('set null');
            $table->string('camera_name');
            $table->enum('camera_type', ['classroom', 'playground', 'library', 'cafeteria', 'laboratory', 'auditorium', 'entrance', 'other']);
            $table->text('description')->nullable();
            $table->string('stream_url');
            $table->string('rtmp_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'offline'])->default('active');
            $table->enum('privacy_level', ['public', 'restricted', 'private', 'disabled'])->default('restricted');
            $table->json('settings')->nullable(); // Camera-specific settings
            $table->timestamp('installation_date');
            $table->string('location_description')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'privacy_level']);
            $table->index(['room_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_cameras');
    }
};