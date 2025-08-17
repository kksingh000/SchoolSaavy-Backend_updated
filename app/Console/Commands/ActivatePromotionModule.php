<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\School;
use App\Models\Module;
use Carbon\Carbon;

class ActivatePromotionModule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'school:activate-promotion-module';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate promotion system module for all schools';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Activating promotion system module for schools...');

        // Get the promotion system module
        $promotionModule = Module::where('slug', 'promotion-system')->first();

        if (!$promotionModule) {
            $this->error('❌ Promotion system module not found!');
            return;
        }

        $this->info("✅ Found promotion module: {$promotionModule->name}");

        // Get all active schools
        $schools = School::where('is_active', true)->get();

        foreach ($schools as $school) {
            // Check if school already has the module
            $existingModule = $school->modules()->where('modules.id', $promotionModule->id)->first();

            if ($existingModule) {
                // Update to active if not already
                if ($existingModule->pivot->status !== 'active') {
                    $school->modules()->updateExistingPivot($promotionModule->id, [
                        'status' => 'active',
                        'activated_at' => Carbon::now(),
                        'expires_at' => Carbon::now()->addYear(),
                    ]);
                    $this->info("✅ Activated promotion module for: {$school->name}");
                } else {
                    $this->info("ℹ️  Promotion module already active for: {$school->name}");
                }
            } else {
                // Attach the module to the school
                $school->modules()->attach($promotionModule->id, [
                    'status' => 'active',
                    'activated_at' => Carbon::now(),
                    'expires_at' => Carbon::now()->addYear(),
                    'settings' => json_encode([])
                ]);
                $this->info("🎉 Added and activated promotion module for: {$school->name}");
            }
        }

        $this->info('🎓 All schools now have access to the promotion system!');

        // Display summary
        $this->info('📊 Module Assignment Summary:');
        foreach ($schools as $school) {
            $activeModules = $school->modules()->wherePivot('status', 'active')->count();
            $this->line("   {$school->name}: {$activeModules} active modules");
        }
    }
}
