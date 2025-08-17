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
        Schema::table('class_student', function (Blueprint $table) {
            // Add academic year tracking to class_student pivot table
            $table->foreignId('academic_year_id')->nullable()->after('class_id')->constrained()->onDelete('set null');
            $table->enum('enrollment_type', ['promoted', 'transferred', 'new_admission', 'repeated'])->default('new_admission')->after('is_active');
            $table->text('enrollment_notes')->nullable()->after('enrollment_type');

            // Add index for academic year queries
            $table->index(['academic_year_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_student', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['academic_year_id', 'enrollment_type', 'enrollment_notes']);
            $table->dropIndex(['academic_year_id', 'is_active']);
        });
    }
};
