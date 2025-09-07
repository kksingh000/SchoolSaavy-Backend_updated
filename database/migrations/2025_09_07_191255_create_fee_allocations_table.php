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
        Schema::create('fee_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('fee_payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('fee_installment_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            
            // Add index for performance with shorter custom names
            $table->index(['school_id', 'fee_payment_id'], 'fa_school_payment_idx');
            $table->index(['fee_installment_id'], 'fa_installment_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_allocations');
    }
};
