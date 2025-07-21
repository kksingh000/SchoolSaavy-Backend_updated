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
        ]);
    }
}
