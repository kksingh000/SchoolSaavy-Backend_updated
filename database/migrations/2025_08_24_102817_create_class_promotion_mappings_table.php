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
        Schema::create('class_promotion_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('to_class_id')->constrained('classes')->onDelete('cascade');
            $table->integer('promotion_order')->default(1); // For ordering the progression
            $table->boolean('is_active')->default(true);
            $table->string('mapping_name')->nullable(); // e.g., "Primary to Secondary", "Grade 10 to 11"
            $table->text('description')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['school_id', 'is_active']);
            $table->index(['from_class_id']);
            $table->index(['to_class_id']);
            $table->index(['promotion_order']);

            // Unique constraint to prevent duplicate mappings
            $table->unique(['school_id', 'from_class_id', 'to_class_id'], 'unique_school_class_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_promotion_mappings');
    }
};
