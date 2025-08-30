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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('type'); // event, result, assignment, attendance, general
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data payload

            // Sender information
            $table->foreignId('sender_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('sender_type')->nullable(); // admin, teacher, system

            // Target information
            $table->string('target_type'); // teacher, parent, student, all
            $table->json('target_ids')->nullable(); // Specific user IDs if not broadcast
            $table->json('target_classes')->nullable(); // Specific class IDs
            $table->json('target_grades')->nullable(); // Specific grade levels

            // Firebase specific
            $table->json('firebase_tokens')->nullable(); // FCM tokens for this notification
            $table->string('firebase_message_id')->nullable(); // Firebase multicast message ID
            $table->json('firebase_response')->nullable(); // Firebase response data

            // Status tracking
            $table->enum('status', ['pending', 'sent', 'failed', 'partial'])->default('pending');
            $table->integer('total_recipients')->default(0);
            $table->integer('successful_sends')->default(0);
            $table->integer('failed_sends')->default(0);

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Settings
            $table->boolean('is_urgent')->default(false);
            $table->boolean('requires_acknowledgment')->default(false);
            $table->boolean('is_broadcast')->default(false);
            $table->string('image_url')->nullable();
            $table->string('action_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['school_id', 'type']);
            $table->index(['school_id', 'target_type']);
            $table->index(['school_id', 'status']);
            $table->index(['scheduled_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
