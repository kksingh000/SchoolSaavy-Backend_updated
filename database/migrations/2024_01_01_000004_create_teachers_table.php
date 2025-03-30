<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->string('employee_id')->unique();
            $table->string('phone');
            $table->date('date_of_birth');
            $table->date('joining_date');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('qualification');
            $table->string('profile_photo')->nullable();
            $table->text('address');
            $table->json('specializations')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('teachers');
    }
}; 