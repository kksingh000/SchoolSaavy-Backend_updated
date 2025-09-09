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
        Schema::table('master_fee_components', function (Blueprint $table) {
            // For MySQL, we need to recreate the enum column with the new value
            DB::statement("ALTER TABLE master_fee_components MODIFY default_frequency ENUM('Monthly', 'Quarterly', 'Yearly', 'One-Time') NOT NULL DEFAULT 'Monthly'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_fee_components', function (Blueprint $table) {
            // Revert back to original enum values
            DB::statement("ALTER TABLE master_fee_components MODIFY default_frequency ENUM('Monthly', 'Quarterly', 'Yearly') NOT NULL DEFAULT 'Monthly'");
        });
    }
};
