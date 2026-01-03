<?php

declare(strict_types=1);

use App\Http\Controllers\AnamnesisResponseController;
use App\Http\Controllers\AnamnesisTemplateController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CreditPackageController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerCreditController;
use App\Http\Controllers\Api\DogController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TrainerController;
use App\Http\Controllers\Api\TrainingAttachmentController;
use App\Http\Controllers\Api\TrainingSessionController;
use App\Http\Controllers\Api\VaccinationController;
use App\Http\Controllers\TrainingLogController;
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
    
    // Training Session Management
    Route::get('/training-sessions', [TrainingSessionController::class, 'index']);
    Route::get('/training-sessions/{trainingSession}', [TrainingSessionController::class, 'show']);
    Route::get('/training-sessions/{trainingSession}/bookings', [TrainingSessionController::class, 'bookings']);
    Route::get('/training-sessions/{trainingSession}/availability', [TrainingSessionController::class, 'availability']);
    
    // Booking Management
    Route::apiResource('bookings', BookingController::class);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
    
    // Course Management
    Route::apiResource('courses', CourseController::class);
    Route::get('/courses/{course}/sessions', [CourseController::class, 'sessions']);
    Route::get('/courses/{course}/participants', [CourseController::class, 'participants']);
    
    // Trainer Management
    Route::apiResource('trainers', TrainerController::class);
    
    // Anamnesis Template Management
    Route::apiResource('anamnesis-templates', AnamnesisTemplateController::class);
    Route::get('/anamnesis-templates/{anamnesisTemplate}/questions', [AnamnesisTemplateController::class, 'questions']);
    
    // Anamnesis Response Management
    Route::apiResource('anamnesis-responses', AnamnesisResponseController::class);
    Route::get('/anamnesis-responses/{anamnesisResponse}/pdf', [AnamnesisResponseController::class, 'downloadPdf']);
    Route::post('/anamnesis-responses/{anamnesisResponse}/complete', [AnamnesisResponseController::class, 'complete']);
    
    // Training Log Management
    Route::apiResource('training-logs', TrainingLogController::class);
    
    // Training Attachment Management
    Route::apiResource('training-attachments', TrainingAttachmentController::class)->except(['update']);
    Route::get('/training-attachments/{trainingAttachment}/download', [TrainingAttachmentController::class, 'download'])->name('training-attachments.download');
    
    // Vaccination Management
    Route::apiResource('vaccinations', VaccinationController::class);
    Route::get('/vaccinations/upcoming/list', [VaccinationController::class, 'upcoming']);
    Route::get('/vaccinations/overdue/list', [VaccinationController::class, 'overdue']);
    
    // Credit Package Management
    Route::apiResource('credit-packages', CreditPackageController::class);
    Route::get('/credit-packages/available/list', [CreditPackageController::class, 'available']);
    
    // Customer Credit Management
    Route::apiResource('customer-credits', CustomerCreditController::class);
    Route::post('/customer-credits/{customerCredit}/use', [CustomerCreditController::class, 'useCredit']);
    Route::get('/customer-credits/active/list', [CustomerCreditController::class, 'active']);
    
    // Invoice Management
    Route::apiResource('invoices', InvoiceController::class);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid']);
    Route::get('/invoices/overdue/list', [InvoiceController::class, 'overdue']);
    
    // Payment Management
    Route::apiResource('payments', PaymentController::class);
    Route::post('/payments/{payment}/mark-completed', [PaymentController::class, 'markAsCompleted']);
});

