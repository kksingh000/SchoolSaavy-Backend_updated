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
        // First add the column without constraint
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('school_id');
        });

        // Set default created_by to the first admin user
        $adminUser = DB::table('users')->where('user_type', 'admin')->first();
        if ($adminUser) {
            DB::table('students')->whereNull('created_by')->update([
                'created_by' => $adminUser->id
            ]);
        }

        // Now add the foreign key constraint
        Schema::table('students', function (Blueprint $table) {
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
