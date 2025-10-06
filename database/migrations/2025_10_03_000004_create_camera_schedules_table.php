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
        Schema::create('camera_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camera_id')->constrained('school_cameras')->onDelete('cascade');
            $table->string('schedule_name');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->enum('schedule_type', ['active', 'restricted', 'maintenance'])->default('active');
            $table->text('description')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['camera_id', 'day_of_week', 'is_active']);
            $table->index(['schedule_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camera_schedules');
    }
};