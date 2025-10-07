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
        // Attendance table indexes for dashboard performance
        Schema::table('attendances', function (Blueprint $table) {
            // Check if index doesn't exist before adding
            if (!$this->indexExists('attendances', 'idx_attendances_school_date')) {
                $table->index(['school_id', 'date'], 'idx_attendances_school_date');
            }
            if (!$this->indexExists('attendances', 'idx_attendances_date_status')) {
                $table->index(['date', 'status'], 'idx_attendances_date_status');
            }
        });

        // Fee payments table indexes for analytics
        Schema::table('fee_payments', function (Blueprint $table) {
            if (!$this->indexExists('fee_payments', 'idx_fee_payments_date_status')) {
                $table->index(['payment_date', 'status'], 'idx_fee_payments_date_status');
            }
            if (!$this->indexExists('fee_payments', 'idx_fee_payments_student_status')) {
                $table->index(['student_id', 'status'], 'idx_fee_payments_student_status');
            }
        });

        // Fee installments table indexes
        Schema::table('fee_installments', function (Blueprint $table) {
            if (!$this->indexExists('fee_installments', 'idx_fee_installments_due_status')) {
                $table->index(['due_date', 'status'], 'idx_fee_installments_due_status');
            }
        });

        // Assessment results table indexes for performance analytics
        Schema::table('assessment_results', function (Blueprint $table) {
            if (!$this->indexExists('assessment_results', 'idx_assessment_results_student_published')) {
                $table->index(['student_id', 'result_published_at'], 'idx_assessment_results_student_published');
            }
            if (!$this->indexExists('assessment_results', 'idx_assessment_results_assessment_published')) {
                $table->index(['assessment_id', 'result_published_at'], 'idx_assessment_results_assessment_published');
            }
        });

        // Students table indexes (if not already exists)
        Schema::table('students', function (Blueprint $table) {
            if (!$this->indexExists('students', 'idx_students_school_active')) {
                $table->index(['school_id', 'is_active'], 'idx_students_school_active');
            }
        });

        // Classes table indexes
        Schema::table('classes', function (Blueprint $table) {
            if (!$this->indexExists('classes', 'idx_classes_school_active')) {
                $table->index(['school_id', 'is_active'], 'idx_classes_school_active');
            }
        });

        // Class student pivot table indexes
        Schema::table('class_student', function (Blueprint $table) {
            if (!$this->indexExists('class_student', 'idx_class_student_active')) {
                $table->index(['class_id', 'student_id', 'is_active'], 'idx_class_student_active');
            }
        });

        // Assignments table indexes
        Schema::table('assignments', function (Blueprint $table) {
            if (!$this->indexExists('assignments', 'idx_assignments_school_teacher')) {
                $table->index(['school_id', 'teacher_id'], 'idx_assignments_school_teacher');
            }
            if (!$this->indexExists('assignments', 'idx_assignments_created_status')) {
                $table->index(['created_at', 'status'], 'idx_assignments_created_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropIndex('idx_assignments_school_teacher');
            $table->dropIndex('idx_assignments_created_status');
        });

        Schema::table('class_student', function (Blueprint $table) {
            $table->dropIndex('idx_class_student_active');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('idx_classes_school_active');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_school_active');
        });

        Schema::table('assessment_results', function (Blueprint $table) {
            $table->dropIndex('idx_assessment_results_student_published');
            $table->dropIndex('idx_assessment_results_assessment_published');
        });

        Schema::table('fee_installments', function (Blueprint $table) {
            $table->dropIndex('idx_fee_installments_due_status');
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex('idx_fee_payments_date_status');
            $table->dropIndex('idx_fee_payments_student_status');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_school_date');
            $table->dropIndex('idx_attendances_date_status');
        });
    }

    /**
     * Helper method to check if an index exists
     */
    private function indexExists($table, $indexName)
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};
