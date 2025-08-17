<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('school_id')
                ->constrained('academic_years')
                ->onDelete('cascade');

            // Add indexes for performance
            $table->index(['school_id', 'academic_year_id', 'student_id', 'date']);
            $table->index(['academic_year_id', 'class_id', 'date']);
        });
    }

    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn('academic_year_id');
            $table->dropIndex(['school_id', 'academic_year_id', 'student_id', 'date']);
            $table->dropIndex(['academic_year_id', 'class_id', 'date']);
        });
    }
};
