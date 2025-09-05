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
        Schema::table('notifications', function (Blueprint $table) {
            // Update the status enum to include all necessary values
            $table->enum('status', ['draft', 'scheduled', 'pending', 'sending', 'sent', 'failed', 'partial'])
                ->default('pending')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('status', ['pending', 'sent', 'failed', 'partial'])
                ->default('pending')
                ->change();
        });
    }
};
