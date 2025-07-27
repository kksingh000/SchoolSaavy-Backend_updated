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
        Schema::create('assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');

            // Marks and grading
            $table->decimal('marks_obtained', 8, 2);
            $table->decimal('percentage', 5, 2);
            $table->string('grade')->nullable(); // A+, A, B+, etc.
            $table->enum('result_status', ['pass', 'fail', 'absent', 'exempted'])->default('pass');

            // Additional details
            $table->text('remarks')->nullable(); // Teacher's remarks
            $table->json('section_wise_marks')->nullable(); // Breakdown by sections/parts
            $table->boolean('is_absent')->default(false);
            $table->text('absence_reason')->nullable();

            // Metadata
            $table->timestamp('result_published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->foreignId('entered_by')->constrained('users'); // Who entered the marks

            $table->timestamps();

            // Constraints
            $table->unique(['assessment_id', 'student_id']); // One result per student per assessment
            $table->index(['student_id', 'result_status']);
            $table->index(['assessment_id', 'percentage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_results');
    }
};
