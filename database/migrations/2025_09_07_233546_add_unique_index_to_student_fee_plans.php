<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_fee_plans', function (Blueprint $table) {
            // Drop any existing duplicate records before adding the unique constraint
            $this->dropDuplicates();
            
            // Add unique index on student_id and fee_structure_id
            $table->unique(['school_id', 'student_id', 'fee_structure_id'], 'student_fee_plan_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_fee_plans', function (Blueprint $table) {
            $table->dropUnique('student_fee_plan_unique');
        });
    }
    
    /**
     * Delete duplicate records before adding the unique constraint
     */
    private function dropDuplicates(): void
    {
        // Find duplicates
        $duplicates = DB::table('student_fee_plans')
            ->select('school_id', 'student_id', 'fee_structure_id', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('school_id', 'student_id', 'fee_structure_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            // Delete all duplicates except the first one
            DB::table('student_fee_plans')
                ->where('school_id', $duplicate->school_id)
                ->where('student_id', $duplicate->student_id)
                ->where('fee_structure_id', $duplicate->fee_structure_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }
    }
};
