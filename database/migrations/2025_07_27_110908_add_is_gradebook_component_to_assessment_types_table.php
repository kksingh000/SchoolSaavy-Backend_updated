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
        // Only add the column if it doesn't already exist
        if (!Schema::hasColumn('assessment_types', 'is_gradebook_component')) {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->boolean('is_gradebook_component')->default(true)->after('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop the column if it exists
        if (Schema::hasColumn('assessment_types', 'is_gradebook_component')) {
            Schema::table('assessment_types', function (Blueprint $table) {
                $table->dropColumn('is_gradebook_component');
            });
        }
    }
};
