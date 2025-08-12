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
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('school_name');
            $table->enum('user_role', ['Teacher', 'Principal', 'Administrator', 'IT Manager', 'Other']);
            $table->string('total_students')->nullable(); // Can be phone number too
            $table->text('message')->nullable();
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->enum('status', ['pending', 'contacted', 'demo_scheduled', 'converted', 'spam'])->default('pending');
            $table->timestamp('contacted_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('spam_score')->nullable(); // Store spam detection results
            $table->timestamps();

            // Indexes for better performance
            $table->index(['status', 'created_at']);
            $table->index('email');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
