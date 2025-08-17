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
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('year_label', 20); // e.g., "2024-25", "2025-26"
            $table->string('display_name'); // e.g., "Academic Year 2024-2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->enum('status', ['upcoming', 'active', 'promotion_period', 'completed'])->default('upcoming');
            $table->date('promotion_start_date')->nullable(); // When promotion process can begin
            $table->date('promotion_end_date')->nullable(); // Deadline for promotion completion
            $table->json('settings')->nullable(); // Additional school-specific settings
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['school_id', 'is_current']);
            $table->index(['school_id', 'status']);
            $table->unique(['school_id', 'year_label']); // One academic year per school per year
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
