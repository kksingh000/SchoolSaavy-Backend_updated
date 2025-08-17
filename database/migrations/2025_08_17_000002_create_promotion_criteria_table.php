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
        Schema::create('promotion_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('to_class_id')->nullable()->constrained('classes')->onDelete('set null');
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');

            // Minimum requirements for promotion
            $table->decimal('minimum_attendance_percentage', 5, 2)->default(75.00);
            $table->decimal('minimum_assignment_average', 5, 2)->default(50.00);
            $table->decimal('minimum_assessment_average', 5, 2)->default(50.00);
            $table->decimal('minimum_overall_percentage', 5, 2)->default(50.00);

            // Weightage for different components (should add up to 100)
            $table->json('promotion_weightages')->nullable(); // {"attendance": 20, "assignments": 40, "assessments": 40}

            // Additional criteria
            $table->integer('minimum_attendance_days')->nullable();
            $table->integer('maximum_disciplinary_actions')->default(5);
            $table->boolean('require_parent_meeting')->default(false);

            // Grace settings
            $table->decimal('grace_marks_allowed', 5, 2)->default(5.00); // Extra marks for borderline cases
            $table->boolean('allow_conditional_promotion')->default(true);

            // Remedial options
            $table->boolean('has_remedial_option')->default(true);
            $table->json('remedial_subjects')->nullable(); // Which subjects allow remedial

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['from_class_id', 'academic_year_id']);
            $table->unique(['school_id', 'from_class_id', 'academic_year_id'], 'unique_promotion_criteria'); // One criteria per class per year
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_criteria');
    }
};
