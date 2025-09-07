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
        Schema::create('fee_structure_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->onDelete('cascade');
            $table->enum('component_name', ['Tuition', 'Transport', 'Lab', 'Misc']);
            $table->decimal('amount', 10, 2);
            $table->enum('frequency', ['Monthly', 'Quarterly', 'Yearly']);
            $table->timestamps();
            
            // Add index for performance
            $table->index(['fee_structure_id', 'component_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_structure_components');
    }
};
