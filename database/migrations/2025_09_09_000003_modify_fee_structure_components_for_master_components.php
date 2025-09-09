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
            // Add reference to master component
            $table->foreignId('master_component_id')->nullable()->after('fee_structure_id')->constrained('master_fee_components')->onDelete('cascade');
            
            // Make component_name nullable since we'll use master component name
            $table->string('component_name')->nullable()->change();
            
            // Add custom_name for school-specific naming
            $table->string('custom_name')->nullable()->after('component_name');
            
            // Add index
            $table->index('master_component_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_structure_components', function (Blueprint $table) {
            $table->dropForeign(['master_component_id']);
            $table->dropColumn(['master_component_id', 'custom_name']);
            $table->string('component_name')->nullable(false)->change();
        });
    }
};
