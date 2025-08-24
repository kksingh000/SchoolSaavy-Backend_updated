<?php

namespace App\Console\Commands;

use App\Services\PromotionValidationService;
use App\Services\PromotionService;
use App\Models\AcademicYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class TestPromotionValidation extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'promotion:test-validation {academic_year_id : The academic year ID to test}';

    /**
     * The console command description.
     */
    protected $description = 'Test promotion validation system to prevent errors';

    protected $validationService;
    protected $promotionService;

    public function __construct()
    {
        parent::__construct();
        $this->validationService = new PromotionValidationService();
        $this->promotionService = new PromotionService();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $academicYearId = $this->argument('academic_year_id');

        // Mock authentication for testing (you should use proper auth in production)
        $this->info("🔍 Testing Promotion Validation System");
        $this->info("📅 Academic Year ID: {$academicYearId}");
        $this->line("");

        try {
            // 1. Test Promotion Readiness Check
            $this->info("1️⃣ Running Comprehensive Promotion Readiness Check...");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            $readiness = $this->promotionService->getPromotionReadiness($academicYearId);

            // Display overall status
            if ($readiness['is_ready']) {
                $this->info("✅ SYSTEM READY FOR PROMOTIONS");
            } else {
                $this->error("❌ SYSTEM NOT READY - PROMOTIONS BLOCKED");
            }

            $this->line("");

            // Show individual checks
            $this->info("📋 Individual System Checks:");
            foreach ($readiness['checks'] as $check => $passed) {
                $status = $passed ? '✅' : '❌';
                $checkName = ucwords(str_replace('_', ' ', $check));
                $this->line("   {$status} {$checkName}");
            }

            $this->line("");

            // Show errors if any
            if (!empty($readiness['errors'])) {
                $this->error("🚨 Critical Errors (Must Fix):");
                foreach ($readiness['errors'] as $error) {
                    $this->line("   • {$error}");
                }
                $this->line("");
            }

            // Show warnings if any
            if (!empty($readiness['warnings'])) {
                $this->warn("⚠️ Warnings (Should Review):");
                foreach ($readiness['warnings'] as $warning) {
                    $this->line("   • {$warning}");
                }
                $this->line("");
            }

            // Show suggestions
            if (!empty($readiness['suggestions'])) {
                $this->info("💡 Suggestions:");
                foreach ($readiness['suggestions'] as $suggestion) {
                    $this->line("   • {$suggestion}");
                }
                $this->line("");
            }

            // Show statistics
            if (!empty($readiness['statistics'])) {
                $this->info("📊 System Statistics:");
                foreach ($readiness['statistics'] as $key => $value) {
                    $keyName = ucwords(str_replace('_', ' ', $key));
                    $this->line("   • {$keyName}: {$value}");
                }
                $this->line("");
            }

            // 2. Test Data Consistency
            $this->info("2️⃣ Checking Data Consistency...");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            $consistency = $this->promotionService->checkDataConsistency($academicYearId);

            if (empty($consistency['same_year_promotions'])) {
                $this->info("✅ No data consistency issues found");
            } else {
                $this->error("❌ Data consistency issues detected:");
                $this->line("");

                foreach ($consistency['same_year_promotions'] as $issue) {
                    $this->line("   🔧 Student: {$issue['student_name']} (ID: {$issue['student_id']})");
                    $this->line("      From: {$issue['from_class']} → To: {$issue['to_class']}");
                    $this->line("      Issue: Promoted within same academic year");
                    $this->line("");
                }
            }

            // 3. Test Validation Enforcement
            $this->info("3️⃣ Testing Validation Enforcement...");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            // Test evaluation validation
            try {
                $this->line("   Testing evaluation validation...");
                $this->promotionService->validatePromotionOperation($academicYearId, 'evaluate');
                $this->info("   ✅ Evaluation validation passed");
            } catch (\Exception $e) {
                $this->error("   ❌ Evaluation validation failed: " . $e->getMessage());
            }

            // Test application validation
            try {
                $this->line("   Testing application validation...");
                $this->promotionService->validatePromotionOperation($academicYearId, 'apply');
                $this->info("   ✅ Application validation passed");
            } catch (\Exception $e) {
                $this->error("   ❌ Application validation failed: " . $e->getMessage());
            }

            $this->line("");
            $this->info("🎯 Validation Test Complete!");

            // Final recommendation
            if ($readiness['is_ready']) {
                $this->line("");
                $this->info("🚀 RECOMMENDATION: System is ready for promotions");
                $this->info("   You can proceed with student evaluations and promotions");
            } else {
                $this->line("");
                $this->error("🛑 RECOMMENDATION: Fix issues before proceeding");
                $this->error("   The validation system will block promotion operations until resolved");
            }
        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
