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
        Schema::table('fee_structure_components', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate master components per fee structure
            $table->unique(['fee_structure_id', 'master_component_id'], 'unique_master_component_per_structure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_structure_components', function (Blueprint $table) {
            $table->dropUnique('unique_master_component_per_structure');
        });
    }
};
