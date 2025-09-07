<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Provide a handler for API authentication failures, but should never be used directly
// All API requests should receive a JSON response, not a redirect
Route::get('login', function () {
    // If this route is directly accessed, return JSON response
    return response()->json([
        'status' => 'error',
        'message' => 'Authentication required',
        'code' => 401
    ], 401);
})->name('login');