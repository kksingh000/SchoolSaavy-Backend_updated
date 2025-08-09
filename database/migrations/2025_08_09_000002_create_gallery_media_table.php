<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gallery_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('gallery_albums')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['photo', 'video']);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->bigInteger('file_size'); // in bytes
            $table->string('thumbnail_path')->nullable(); // For video thumbnails
            $table->json('metadata')->nullable(); // For storing dimensions, duration etc
            $table->integer('views_count')->default(0);
            $table->integer('downloads_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['active', 'processing', 'failed', 'archived'])->default('active');
            $table->timestamps();

            $table->index(['album_id', 'type']);
            $table->index('status');
            $table->index('is_featured');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gallery_media');
    }
};
