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
        Schema::table('assignment_submissions', function (Blueprint $table) {
            // Add composite index for assignment_id and status for faster submission counting
            $table->index(['assignment_id', 'status'], 'idx_assignment_submissions_assignment_status');

            // Add index for assignment_id alone for general assignment queries
            $table->index('assignment_id', 'idx_assignment_submissions_assignment_id');

            // Add index for status for status filtering
            $table->index('status', 'idx_assignment_submissions_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_assignment_submissions_assignment_status');
            $table->dropIndex('idx_assignment_submissions_assignment_id');
            $table->dropIndex('idx_assignment_submissions_status');
        });
    }
};
