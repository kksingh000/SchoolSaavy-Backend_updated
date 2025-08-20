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
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('key'); // Setting key like 'admission_number_prefix', 'app_banner_url'
            $table->text('value')->nullable(); // Setting value (JSON for complex values)
            $table->string('type')->default('string'); // string, integer, boolean, json, file_url
            $table->string('category')->default('general'); // general, admission, branding, academic, etc.
            $table->string('description')->nullable(); // Human-readable description
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index(['school_id', 'key']);
            $table->index(['school_id', 'category']);
            $table->unique(['school_id', 'key']); // Each school can have only one value per setting key
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
