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
        Schema::table('student_fees', function (Blueprint $table) {
            $table->string('component_type')->nullable()->after('fee_structure_id');
            $table->string('component_name')->nullable()->after('component_type');
            $table->boolean('is_mandatory')->default(true)->after('notes');

            // Add index for better performance
            $table->index(['fee_structure_id', 'component_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_fees', function (Blueprint $table) {
            $table->dropIndex(['fee_structure_id', 'component_type']);
            $table->dropColumn(['component_type', 'component_name', 'is_mandatory']);
        });
    }
};
