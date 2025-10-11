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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_type')->nullable(); // admin, teacher, parent, student, super_admin
            $table->string('user_name')->nullable(); // Cache user name for deleted users
            
            // Activity details
            $table->string('action'); // create, update, delete, view, login, logout, etc.
            $table->string('module'); // assessment, student, teacher, class, etc.
            $table->string('description'); // Human-readable description
            
            // Target entity (what was acted upon)
            $table->string('subject_type')->nullable(); // Model class name
            $table->unsignedBigInteger('subject_id')->nullable(); // Model ID
            
            // Additional data
            $table->json('properties')->nullable(); // Changed data, metadata, etc.
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            // Performance tracking
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            $table->unsignedInteger('response_time_ms')->nullable(); // Track performance
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['school_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'module']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
