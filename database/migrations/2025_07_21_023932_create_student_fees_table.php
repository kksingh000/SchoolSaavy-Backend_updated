<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Migration disabled as we're using the new fee management system
        // Schema::create('student_fees', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('student_id')->constrained()->onDelete('cascade');
        //     $table->foreignId('fee_structure_id')->constrained()->onDelete('cascade');
        //     $table->decimal('amount', 10, 2);
        //     $table->date('due_date');
        //     $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
        //     $table->decimal('concession_amount', 10, 2)->default(0);
        //     $table->decimal('fine_amount', 10, 2)->default(0);
        //     $table->text('notes')->nullable();
        //     $table->timestamps();
        // });
    }

    public function down()
    {
        Schema::dropIfExists('student_fees');
    }
};
