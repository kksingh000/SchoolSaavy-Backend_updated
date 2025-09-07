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
        Schema::create('fee_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_fee_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('component_id')->constrained('fee_structure_components')->onDelete('cascade');
            $table->integer('installment_no');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['Pending', 'Paid', 'Overdue'])->default('Pending');
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->timestamps();
            
            // Add index for performance with shorter custom names
            $table->index(['school_id', 'student_fee_plan_id', 'status'], 'fi_school_plan_status_idx');
            $table->index(['due_date', 'status'], 'fi_due_date_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_installments');
    }
};
