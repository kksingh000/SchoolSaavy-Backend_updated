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
        Schema::create('admin_menus', function (Blueprint $table) {
            $table->string('id')->primary(); // Using string ID like the TS version
            $table->string('name'); // Translation key (e.g., "sys.nav.dashboard")
            $table->string('code'); // Unique code identifier
            $table->string('parent_id')->nullable(); // Parent menu ID
            $table->enum('type', ['GROUP', 'MENU', 'CATALOGUE']); // Menu type
            $table->string('icon')->nullable(); // Icon identifier
            $table->string('path')->nullable(); // Frontend route path
            $table->string('component')->nullable(); // Frontend component path
            $table->json('meta')->nullable(); // Additional metadata
            $table->integer('sort_order')->default(0); // For ordering
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint (self-referencing)
            $table->foreign('parent_id')->references('id')->on('admin_menus')->onDelete('cascade');

            // Indexes
            $table->index(['parent_id', 'type', 'sort_order']);
            $table->index(['code']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_menus');
    }
};
