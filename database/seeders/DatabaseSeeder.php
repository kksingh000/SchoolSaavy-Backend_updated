<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ModuleSeeder::class,
            SchoolSeeder::class,
            SchoolAdminSeeder::class,
            TeacherSeeder::class,
            ClassSeeder::class,
            ParentStudentSeeder::class, // This will handle both parents and students
            AssessmentTypeSeeder::class,
            AssessmentSeeder::class,
            AssessmentResultSeeder::class,
            CambridgeSchoolSeeder::class, // Comprehensive test data for Cambridge International School
            GallerySeeder::class, // Gallery dummy data with public URLs
        ]);
    }
}
