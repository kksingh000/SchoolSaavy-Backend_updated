<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->integer('media_count')->default(0);
            $table->string('cover_image')->nullable();
            $table->boolean('is_public')->default(true);
            $table->json('visibility_settings')->nullable(); // For controlling who can see the album
            $table->timestamps();

            $table->index(['school_id', 'class_id']);
            $table->index(['school_id', 'event_id']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gallery_albums');
    }
};
