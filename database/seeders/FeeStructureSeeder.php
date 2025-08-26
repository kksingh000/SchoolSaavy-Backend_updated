<?php

namespace Database\Seeders;

use App\Models\FeeStructure;
use App\Models\School;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeeStructureSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $schools = School::all();

        foreach ($schools as $school) {
            // Get current academic year for this school
            $academicYear = AcademicYear::where('school_id', $school->id)
                ->where('is_current', true)
                ->first();

            if (!$academicYear) {
                continue; // Skip if no academic year exists
            }

            // Get classes for this school
            $classes = ClassRoom::where('school_id', $school->id)->get();

            foreach ($classes as $class) {
                // Create Annual Fee Structure
                FeeStructure::create([
                    'school_id' => $school->id,
                    'name' => "Annual Fees - {$class->name}",
                    'class_id' => $class->id,
                    'academic_year_id' => $academicYear->id,
                    'academic_year' => $academicYear->year_label, // Backward compatibility
                    'fee_components' => [
                        [
                            'type' => 'tuition',
                            'name' => 'Tuition Fee',
                            'amount' => $this->getTuitionFee($class->grade_level),
                            'due_date' => $academicYear->start_date->addDays(30)->format('Y-m-d'),
                            'is_mandatory' => true,
                            'description' => 'Monthly tuition fee for academic instruction'
                        ],
                        [
                            'type' => 'development',
                            'name' => 'Development Fee',
                            'amount' => 2000,
                            'due_date' => $academicYear->start_date->addDays(15)->format('Y-m-d'),
                            'is_mandatory' => true,
                            'description' => 'Annual development and infrastructure fee'
                        ],
                        [
                            'type' => 'activity',
                            'name' => 'Activity Fee',
                            'amount' => 1500,
                            'due_date' => $academicYear->start_date->addDays(45)->format('Y-m-d'),
                            'is_mandatory' => true,
                            'description' => 'Fee for extracurricular activities and events'
                        ],
                        [
                            'type' => 'transport',
                            'name' => 'Transport Fee',
                            'amount' => 3000,
                            'due_date' => $academicYear->start_date->addDays(30)->format('Y-m-d'),
                            'is_mandatory' => false,
                            'description' => 'Monthly transport fee (optional)'
                        ]
                    ],
                    'total_amount' => $this->getTuitionFee($class->grade_level) + 2000 + 1500 + 3000,
                    'is_active' => true,
                    'description' => "Complete annual fee structure for {$class->name} covering all components"
                ]);

                // Create Quarterly Fee Structure (Alternative)
                FeeStructure::create([
                    'school_id' => $school->id,
                    'name' => "Quarterly Fees - {$class->name}",
                    'class_id' => $class->id,
                    'academic_year_id' => $academicYear->id,
                    'academic_year' => $academicYear->year_label,
                    'fee_components' => [
                        [
                            'type' => 'tuition',
                            'name' => 'Q1 Tuition Fee',
                            'amount' => $this->getTuitionFee($class->grade_level) * 3, // 3 months
                            'due_date' => $academicYear->start_date->addDays(15)->format('Y-m-d'),
                            'is_mandatory' => true,
                            'description' => 'First quarter tuition fee'
                        ],
                        [
                            'type' => 'examination',
                            'name' => 'Examination Fee',
                            'amount' => 800,
                            'due_date' => $academicYear->start_date->addDays(60)->format('Y-m-d'),
                            'is_mandatory' => true,
                            'description' => 'Quarterly examination and assessment fee'
                        ]
                    ],
                    'total_amount' => ($this->getTuitionFee($class->grade_level) * 3) + 800,
                    'is_active' => false, // Not active by default
                    'description' => "Quarterly payment option for {$class->name} - first quarter"
                ]);
            }

            // Create General Fees (not class-specific)
            FeeStructure::create([
                'school_id' => $school->id,
                'name' => 'Admission & Registration Fees',
                'class_id' => null, // Not class-specific
                'academic_year_id' => $academicYear->id,
                'academic_year' => $academicYear->year_label,
                'fee_components' => [
                    [
                        'type' => 'admission',
                        'name' => 'Admission Fee',
                        'amount' => 5000,
                        'due_date' => null, // No specific due date
                        'is_mandatory' => true,
                        'description' => 'One-time admission fee for new students'
                    ],
                    [
                        'type' => 'registration',
                        'name' => 'Annual Registration',
                        'amount' => 2000,
                        'due_date' => $academicYear->start_date->format('Y-m-d'),
                        'is_mandatory' => true,
                        'description' => 'Annual registration and documentation fee'
                    ],
                    [
                        'type' => 'security',
                        'name' => 'Security Deposit',
                        'amount' => 3000,
                        'due_date' => null,
                        'is_mandatory' => true,
                        'description' => 'Refundable security deposit'
                    ]
                ],
                'total_amount' => 10000,
                'is_active' => true,
                'description' => 'One-time fees for new admissions and annual registration'
            ]);
        }
    }

    /**
     * Get tuition fee based on grade level
     */
    private function getTuitionFee($gradeLevel): int
    {
        return match (true) {
            $gradeLevel <= 2 => 8000,  // Primary lower
            $gradeLevel <= 5 => 10000, // Primary upper
            $gradeLevel <= 8 => 12000, // Middle school
            $gradeLevel <= 10 => 15000, // High school
            $gradeLevel <= 12 => 18000, // Senior secondary
            default => 10000
        };
    }
}
