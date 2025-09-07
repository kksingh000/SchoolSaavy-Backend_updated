<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First create assessment_types table for school-configurable test types
        Schema::create('assessment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('name'); // UT, FA, SA, Mid-term, Final, etc. (school defines)
            $table->string('display_name'); // Unit Test, Formative Assessment, etc.
            $table->text('description')->nullable();
            $table->string('frequency'); // monthly, quarterly, half_yearly, yearly, custom
            $table->integer('weightage_percentage')->default(0); // % contribution to final grade
            $table->integer('sort_order')->default(0); // Display order
            $table->boolean('is_active')->default(true);
            $table->boolean('is_gradebook_component')->default(true); // Whether this contributes to final grade
            $table->json('settings')->nullable(); // Custom settings per school
            $table->timestamps();

            $table->unique(['school_id', 'name']); // School can't have duplicate type names
            $table->index(['school_id', 'is_active']);
        });

        // Then create the flexible assessments table
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('assessment_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');

            $table->string('title'); // School defines: "UT-1 Mathematics", "FA-2 English", etc.
            $table->string('code')->nullable(); // Optional: UT1-MAT-2025, FA2-ENG-2025
            $table->text('description')->nullable();

            // Scheduling
            $table->date('assessment_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes');

            // Marking scheme
            $table->integer('total_marks');
            $table->integer('passing_marks');
            $table->json('marking_scheme')->nullable(); // Flexible marking criteria

            // Content
            $table->text('syllabus_covered')->nullable();
            $table->json('topics')->nullable(); // Array of topics/chapters
            $table->json('instructions')->nullable();

            // Status and metadata
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'results_published', 'cancelled'])->default('scheduled');
            $table->string('academic_year'); // 2024-25, 2025-26
            $table->string('term')->nullable(); // School can define: Term 1, Semester 1, etc.
            $table->boolean('is_active')->default(true);

            // Additional school-specific fields
            $table->json('custom_fields')->nullable(); // Schools can add their own fields

            $table->timestamps();

            // Indexes
            $table->index(['school_id', 'class_id', 'assessment_date']);
            $table->index(['assessment_type_id', 'status']);
            $table->index(['academic_year', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('assessment_types');
    }
};
