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
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('to_class_id')->nullable()->constrained('classes')->onDelete('set null');

            // Promotion decision
            $table->enum('promotion_status', [
                'pending',           // Not yet evaluated
                'promoted',          // Successfully promoted
                'conditionally_promoted', // Promoted with conditions
                'failed',            // Not promoted - repeat class
                'transferred',       // Moved to different school/section
                'graduated',         // Completed final year
                'withdrawn'          // Left school
            ])->default('pending');

            // Evaluation scores
            $table->decimal('attendance_percentage', 5, 2)->nullable();
            $table->decimal('assignment_average', 5, 2)->nullable();
            $table->decimal('assessment_average', 5, 2)->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('final_percentage', 5, 2)->nullable(); // Weighted final score

            // Criteria evaluation
            $table->json('criteria_details')->nullable(); // Detailed breakdown of how criteria were met/missed
            $table->boolean('attendance_criteria_met')->default(false);
            $table->boolean('assignment_criteria_met')->default(false);
            $table->boolean('assessment_criteria_met')->default(false);
            $table->boolean('overall_criteria_met')->default(false);

            // Administrative details
            $table->text('promotion_reason')->nullable(); // Why promoted/failed
            $table->text('admin_comments')->nullable(); // Additional comments from admin
            $table->boolean('is_manual_override')->default(false); // Was this decision overridden manually
            $table->text('override_reason')->nullable(); // Reason for manual override
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('evaluation_date')->nullable();
            $table->timestamp('approval_date')->nullable();

            // Remedial tracking
            $table->boolean('requires_remedial')->default(false);
            $table->json('remedial_subjects')->nullable(); // Which subjects need remedial work
            $table->date('remedial_deadline')->nullable();
            $table->enum('remedial_status', ['not_required', 'pending', 'in_progress', 'completed', 'failed'])->default('not_required');

            // Parent communication
            $table->boolean('parent_notified')->default(false);
            $table->timestamp('parent_notification_date')->nullable();
            $table->boolean('parent_meeting_required')->default(false);
            $table->boolean('parent_meeting_completed')->default(false);
            $table->timestamp('parent_meeting_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['student_id', 'academic_year_id']);
            $table->index(['promotion_status']);
            $table->index(['from_class_id', 'academic_year_id']);
            $table->index(['requires_remedial']);

            // Unique constraint - one promotion record per student per academic year
            $table->unique(['student_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
