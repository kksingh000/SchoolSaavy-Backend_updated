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
        Schema::create('student_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_import_id')->constrained()->onDelete('cascade');
            $table->integer('row_number');
            $table->json('row_data'); // The original row data from CSV
            $table->json('errors'); // Validation errors for this row
            $table->timestamps();

            $table->index(['student_import_id', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_import_errors');
    }
};
