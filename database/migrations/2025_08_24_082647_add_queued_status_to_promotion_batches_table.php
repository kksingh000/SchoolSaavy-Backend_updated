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
        // First, add the 'queued' option to the enum
        DB::statement("ALTER TABLE promotion_batches MODIFY COLUMN status ENUM('created', 'queued', 'processing', 'completed', 'failed') DEFAULT 'created'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the 'queued' option from the enum
        DB::statement("ALTER TABLE promotion_batches MODIFY COLUMN status ENUM('created', 'processing', 'completed', 'failed') DEFAULT 'created'");
    }
};
