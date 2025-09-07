<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FeeManagementController;

/*
|--------------------------------------------------------------------------
| Fee Management API Routes
|--------------------------------------------------------------------------
|
| Routes for the fee management system
|
*/

// Fee Management Routes
Route::middleware(['auth:sanctum', 'school.status', 'inject.school'])->group(function () {
    // Fee Structures
    Route::prefix('fee-management/structures')->group(function () {
        Route::get('/', [FeeManagementController::class, 'listFeeStructures']);
        Route::post('/', [FeeManagementController::class, 'storeFeeStructure']);
        Route::get('{id}', [FeeManagementController::class, 'showFeeStructure']);
        Route::put('{id}', [FeeManagementController::class, 'updateFeeStructure']);
        Route::delete('{id}', [FeeManagementController::class, 'destroyFeeStructure']);
    });

    // Student Fee Plans
    Route::prefix('fee-management/student-plans')->group(function () {
        Route::get('/', [FeeManagementController::class, 'listStudentFeePlans']);
        Route::post('/', [FeeManagementController::class, 'storeStudentFeePlan']);
        Route::get('{id}', [FeeManagementController::class, 'showStudentFeePlan']);
        Route::put('{id}', [FeeManagementController::class, 'updateStudentFeePlan']);
        Route::delete('{id}', [FeeManagementController::class, 'destroyStudentFeePlan']);
    });

    // Payments
    Route::prefix('fee-management/payments')->group(function () {
        Route::post('/', [FeeManagementController::class, 'processPayment']);
        Route::get('due-installments', [FeeManagementController::class, 'getDueInstallments']);
        Route::get('student-fee-details', [FeeManagementController::class, 'getStudentFeeDetails']);
        Route::get('{studentId}/student-fee-details', [FeeManagementController::class, 'getDetailedStudentFeeDetails']);
    });

    // Student-specific endpoints
    Route::prefix('fee-management/students')->group(function () {
        Route::get('{studentId}/payments', [FeeManagementController::class, 'getStudentPaymentHistory']);
        Route::get('{studentId}/summary', [FeeManagementController::class, 'getStudentFeeSummary']);
    });
});
