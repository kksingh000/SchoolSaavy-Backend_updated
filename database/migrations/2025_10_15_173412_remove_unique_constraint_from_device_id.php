<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Remove the unique constraint on device_id to allow multiple devices with same ID 
     * but different users, and rely on composite unique key for user_id and device_id.
     */
    public function up(): void
    {
        Schema::table('user_device_tokens', function (Blueprint $table) {
            // Drop the unique constraint on device_id
            $table->dropUnique('user_device_tokens_device_id_unique');
            
            // Keep the composite unique constraint on user_id and device_id
            // This ensures a user can't register the same device multiple times
            // But allows different users to register devices with the same ID
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_device_tokens', function (Blueprint $table) {
            // Re-add the unique constraint on device_id
            $table->unique('device_id');
        });
    }
};
