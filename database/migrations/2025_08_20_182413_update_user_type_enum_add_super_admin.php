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
        // First, migrate existing 'admin' users to 'school_admin'
        DB::table('users')
            ->where('user_type', 'admin')
            ->update(['user_type' => 'school_admin']);

        // Update the enum to include 'super_admin' and remove 'admin'
        DB::statement("ALTER TABLE users MODIFY user_type ENUM('super_admin', 'school_admin', 'teacher', 'parent', 'student')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, migrate 'school_admin' users back to 'admin' if needed
        DB::table('users')
            ->where('user_type', 'school_admin')
            ->update(['user_type' => 'admin']);

        // Revert the enum back to original state
        DB::statement("ALTER TABLE users MODIFY user_type ENUM('admin', 'teacher', 'parent', 'school_admin')");
    }
};
