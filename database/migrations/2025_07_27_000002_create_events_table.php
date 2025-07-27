<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', [
                'announcement',
                'holiday',
                'exam',
                'meeting',
                'sports',
                'cultural',
                'academic',
                'emergency',
                'other'
            ]);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->json('target_audience')->nullable(); // ['all', 'students', 'teachers', 'parents', 'staff']
            $table->json('affected_classes')->nullable(); // Class IDs if specific to certain classes
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly', 'yearly'])->nullable();
            $table->date('recurrence_end_date')->nullable();
            $table->boolean('requires_acknowledgment')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['school_id', 'event_date']);
            $table->index(['school_id', 'type']);
            $table->index(['event_date', 'is_published']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('events');
    }
};
