<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('school_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->date('activated_at');
            $table->date('expires_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'expired', 'trial'])->default('trial');
            $table->json('settings')->nullable(); // Module-specific settings
            $table->timestamps();

            $table->unique(['school_id', 'module_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('school_modules');
    }
};
