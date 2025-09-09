<?php

namespace Database\Seeders;

use App\Models\MasterFeeComponent;
use Illuminate\Database\Seeder;

class MasterFeeComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $components = [
            // Academic Components (Required)
            [
                'name' => 'Tuition Fee',
                'description' => 'Monthly tuition fee for academic instruction',
                'category' => 'academic',
                'is_required' => true,
                'default_frequency' => 'Monthly',
            ],
            [
                'name' => 'Admission Fee',
                'description' => 'One-time admission fee for new students',
                'category' => 'academic',
                'is_required' => true,
                'default_frequency' => 'One-Time',
            ],
            [
                'name' => 'Development Fee',
                'description' => 'Annual fee for school infrastructure development',
                'category' => 'academic',
                'is_required' => true,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Examination Fee',
                'description' => 'Fee for conducting examinations and assessments',
                'category' => 'academic',
                'is_required' => true,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Registration Fee',
                'description' => 'One-time registration fee for new academic year',
                'category' => 'academic',
                'is_required' => true,
                'default_frequency' => 'One-Time',
            ],

            // Transport Components (Optional)
            [
                'name' => 'Bus Fee',
                'description' => 'Monthly school bus transportation fee',
                'category' => 'transport',
                'is_required' => false,
                'default_frequency' => 'Monthly',
            ],
            [
                'name' => 'Van Service Fee',
                'description' => 'Monthly van transportation fee',
                'category' => 'transport',
                'is_required' => false,
                'default_frequency' => 'Monthly',
            ],

            // Library Components (Optional)
            [
                'name' => 'Library Fee',
                'description' => 'Annual library access and maintenance fee',
                'category' => 'library',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Book Rental Fee',
                'description' => 'Fee for renting textbooks from school library',
                'category' => 'library',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],

            // Sports & Activities (Optional)
            [
                'name' => 'Sports Fee',
                'description' => 'Annual fee for sports activities and equipment',
                'category' => 'sports',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Annual Function Fee',
                'description' => 'Fee for annual day and cultural events',
                'category' => 'events',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Excursion Fee',
                'description' => 'Fee for educational trips and excursions',
                'category' => 'events',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],

            // Technology Components (Optional)
            [
                'name' => 'Computer Lab Fee',
                'description' => 'Annual fee for computer lab access and maintenance',
                'category' => 'technology',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Internet Fee',
                'description' => 'Fee for internet access and digital learning resources',
                'category' => 'technology',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],

            // Food & Catering (Optional)
            [
                'name' => 'Lunch Fee',
                'description' => 'Monthly fee for school lunch program',
                'category' => 'catering',
                'is_required' => false,
                'default_frequency' => 'Monthly',
            ],
            [
                'name' => 'Snack Fee',
                'description' => 'Monthly fee for snack program',
                'category' => 'catering',
                'is_required' => false,
                'default_frequency' => 'Monthly',
            ],

            // Security & Safety (Optional)
            [
                'name' => 'Security Fee',
                'description' => 'Annual fee for school security services',
                'category' => 'security',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Insurance Fee',
                'description' => 'Annual student insurance fee',
                'category' => 'security',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],

            // Special Services (Optional)
            [
                'name' => 'Uniform Fee',
                'description' => 'Annual fee for school uniforms',
                'category' => 'services',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Stationery Fee',
                'description' => 'Annual fee for school stationery supplies',
                'category' => 'services',
                'is_required' => false,
                'default_frequency' => 'Yearly',
            ],
            [
                'name' => 'Late Fee',
                'description' => 'Penalty fee for late payment of fees',
                'category' => 'penalty',
                'is_required' => false,
                'default_frequency' => 'Monthly',
            ],
        ];

        foreach ($components as $component) {
            MasterFeeComponent::firstOrCreate(
                ['name' => $component['name']],
                $component
            );
        }
    }
}
