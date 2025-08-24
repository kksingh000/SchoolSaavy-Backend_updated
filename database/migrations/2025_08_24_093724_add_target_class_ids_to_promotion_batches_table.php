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
        Schema::table('promotion_batches', function (Blueprint $table) {
            $table->json('target_class_ids')->nullable()->after('class_filters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_batches', function (Blueprint $table) {
            $table->dropColumn('target_class_ids');
        });
    }
};
