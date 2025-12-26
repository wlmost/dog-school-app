<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// Protected routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
    
    // Admin only routes
    Route::middleware('can:admin')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
    });
    
    // Customer Management
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{customer}/dogs', [CustomerController::class, 'dogs']);
    Route::get('/customers/{customer}/bookings', [CustomerController::class, 'bookings']);
    Route::get('/customers/{customer}/invoices', [CustomerController::class, 'invoices']);
    Route::get('/customers/{customer}/credits', [CustomerController::class, 'credits']);
    
    // Dog Management
    Route::apiResource('dogs', DogController::class);
    Route::get('/dogs/{dog}/vaccinations', [DogController::class, 'vaccinations']);
    Route::get('/dogs/{dog}/training-logs', [DogController::class, 'trainingLogs']);
    Route::get('/dogs/{dog}/bookings', [DogController::class, 'bookings']);
});

