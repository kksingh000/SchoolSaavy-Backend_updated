<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        School::firstOrCreate(
            ['code' => 'CIS001'],
            [
                'name' => 'Cambridge International School',
                'code' => 'CIS001',
                'address' => '123 Education Street, Academic District',
                'phone' => '1234567890',
                'email' => 'info@cambridgeschool.com',
                'website' => 'www.cambridgeschool.com',
                'is_active' => true,
            ]
        );
    }
} 