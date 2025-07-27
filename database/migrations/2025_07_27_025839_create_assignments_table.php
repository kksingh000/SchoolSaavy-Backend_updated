<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->enum('type', ['homework', 'project', 'quiz', 'classwork', 'assessment'])->default('homework');
            $table->enum('status', ['draft', 'published', 'completed', 'graded'])->default('draft');
            $table->date('assigned_date');
            $table->date('due_date');
            $table->time('due_time')->nullable();
            $table->integer('max_marks')->default(100);
            $table->json('attachments')->nullable(); // Store file paths/URLs
            $table->boolean('allow_late_submission')->default(false);
            $table->text('grading_criteria')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for better performance
            $table->index(['school_id', 'class_id', 'subject_id']);
            $table->index(['teacher_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('assignments');
    }
};
