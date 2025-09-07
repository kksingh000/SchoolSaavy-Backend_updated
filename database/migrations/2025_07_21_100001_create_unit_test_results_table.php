<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_test_id')->references('id')->on('assessments')->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');

            $table->integer('marks_obtained');
            $table->decimal('percentage', 5, 2); // e.g., 85.50%
            $table->string('grade'); // A+, A, B+, B, C+, C, D, F
            $table->enum('result_status', ['pass', 'fail', 'absent', 'exempted'])->default('pass');
            $table->boolean('is_absent')->default(false);
            $table->text('remarks')->nullable(); // Teacher's remarks
            $table->json('subject_wise_marks')->nullable(); // For subjects with multiple sections

            // Performance indicators
            $table->integer('rank_in_class')->nullable();
            $table->decimal('class_average', 5, 2)->nullable();
            $table->boolean('is_improvement_needed')->default(false);

            $table->timestamps();

            // Unique constraint - one result per student per test
            $table->unique(['unit_test_id', 'student_id']);

            // Indexes
            $table->index(['student_id', 'percentage']);
            $table->index(['unit_test_id', 'marks_obtained']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_test_results');
    }
};
