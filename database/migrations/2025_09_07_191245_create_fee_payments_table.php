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
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('fee_installment_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->enum('payment_method', ['Cash', 'Bank Transfer', 'Cheque', 'Card', 'Online', 'UPI']);
            $table->string('transaction_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->string('receipt_number')->unique();
            $table->text('notes')->nullable();
            $table->enum('status', ['Pending', 'Completed', 'Failed', 'Refunded'])->default('Completed');
            $table->timestamps();
            $table->softDeletes();
            
            // Add index for performance with shorter custom names
            $table->index(['school_id', 'student_id'], 'fp_school_student_idx');
            $table->index(['fee_installment_id', 'status'], 'fp_installment_status_idx');
            $table->index('receipt_number', 'fp_receipt_number_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
