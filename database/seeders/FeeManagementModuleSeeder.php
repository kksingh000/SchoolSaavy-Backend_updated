<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class FeeManagementModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if fee management module already exists
        if (!Module::where('name', 'fee-management')->exists()) {
            Module::create([
                'name' => 'fee-management',
                'display_name' => 'Fee Management',
                'description' => 'Manage fee structures, student fee plans, and payment processing',
                'icon' => 'money-bill',
                'is_active' => true,
                'is_premium' => true,
                'order' => 8,
                'group' => 'finance',
            ]);
            
            $this->command->info('Fee Management module added successfully.');
        } else {
            $this->command->info('Fee Management module already exists.');
        }
    }
}
