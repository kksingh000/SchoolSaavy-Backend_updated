<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->text('content')->nullable(); // Text submission
            $table->json('attachments')->nullable(); // File submissions
            $table->enum('status', ['pending', 'submitted', 'graded', 'returned'])->default('pending');
            $table->datetime('submitted_at')->nullable();
            $table->decimal('marks_obtained', 5, 2)->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->json('grading_details')->nullable(); // Detailed grading breakdown
            $table->datetime('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('teachers')->onDelete('set null');
            $table->boolean('is_late_submission')->default(false);
            $table->timestamps();

            // Ensure one submission per student per assignment
            $table->unique(['assignment_id', 'student_id']);

            // Indexes for performance
            $table->index(['assignment_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index('submitted_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
