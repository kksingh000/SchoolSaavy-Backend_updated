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
        Schema::create('student_fee_plan_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_fee_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('component_id')->constrained('fee_structure_components')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->decimal('custom_amount', 10, 2)->nullable();
            $table->timestamps();
            
            // Add index for performance with a shorter custom name
            $table->index(['student_fee_plan_id', 'component_id'], 'sfpc_plan_component_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_fee_plan_components');
    }
};
