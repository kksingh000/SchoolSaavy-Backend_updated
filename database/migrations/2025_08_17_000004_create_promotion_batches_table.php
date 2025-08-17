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
        Schema::create('promotion_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
            $table->string('batch_name'); // e.g., "Grade 10 to 11 Promotion - 2025"
            $table->text('description')->nullable();

            // Batch processing details
            $table->enum('status', ['created', 'processing', 'completed', 'failed'])->default('created');
            $table->integer('total_students')->default(0);
            $table->integer('processed_students')->default(0);
            $table->integer('promoted_students')->default(0);
            $table->integer('failed_students')->default(0);
            $table->integer('pending_students')->default(0);

            // Processing metadata
            $table->json('class_filters')->nullable(); // Which classes were included
            $table->json('processing_log')->nullable(); // Log of processing steps
            $table->text('error_log')->nullable(); // Any errors encountered
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['status']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_batches');
    }
};
