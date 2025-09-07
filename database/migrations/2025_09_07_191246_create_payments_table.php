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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['Cash', 'UPI', 'Card', 'BankTransfer']);
            $table->dateTime('date');
            $table->enum('status', ['Success', 'Failed', 'Pending'])->default('Success');
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Add index for performance
            $table->index(['school_id', 'student_id', 'status']);
            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
