<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('login', function () {
    return response()->json([
        'message' => 'Unauthenticated.',
        'status' => 401
    ], 401);
})->name('login');