<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('occupation')->nullable();
            $table->string('profile_photo')->nullable();
            $table->text('address');
            $table->string('relationship'); // father, mother, guardian
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parents');
    }
}; 