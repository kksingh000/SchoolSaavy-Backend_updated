<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if we're using MySQL
        if (DB::getDriverName() === 'mysql') {
            // Alter the enum to include 'school_admin'
            DB::statement("ALTER TABLE users MODIFY user_type ENUM('admin', 'teacher', 'parent', 'school_admin')");
        } else {
            // For SQLite and other databases, we need to handle this differently
            // Since we're in testing and using RefreshDatabase, this will be handled by the original migration
            // being run with the updated user_type values
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE users MODIFY user_type ENUM('admin', 'teacher', 'parent')");
    }
};
