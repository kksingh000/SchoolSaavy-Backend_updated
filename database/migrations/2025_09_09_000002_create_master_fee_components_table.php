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
        // Create master fee components table
        Schema::create('master_fee_components', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "Tuition Fee", "Transport Fee"
            $table->string('description')->nullable();
            $table->string('category')->default('academic'); // academic, transport, library, sports, etc.
            $table->boolean('is_required')->default(true); // Default requirement level
            $table->enum('default_frequency', ['Monthly', 'Quarterly', 'Yearly'])->default('Monthly');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['category', 'is_active']);
            $table->index('is_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_fee_components');
    }
};
