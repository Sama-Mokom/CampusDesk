<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RequestStageController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\AttachmentController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show']);
});
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/requests/{request}', [RequestController::class, 'show']);
});
Route::middleware(['auth:sanctum', 'student', 'throttle:60,1'])->group(function () {
    Route::get('/requests', [RequestController::class, 'index']);
    // Route::get('/requests/{request}', [RequestController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'student', 'throttle:10,1'])->group(function () {
    Route::post('/requests', [RequestController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'staff', 'throttle:60,1'])->group(function () {
    // staff-only routes go here
    Route::get('/requests/{request}/stages', [RequestStageController::class, 'forRequest']);
    Route::get('/stages', [RequestStageController::class, 'index']);
    Route::get('/stages/my-cases', [RequestStageController::class, 'myCases']);
    Route::post('requests/{docRequest}/stages/{stage}/claim', [RequestStageController::class, 'claim']);
    Route::patch('requests/{docRequest}/stages/{stage}/resolve', [RequestStageController::class, 'resolve']);
});
Route::middleware(['auth:sanctum', 'dept_admin'])->group(function () {
    // dept_admin routes go here
});

Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    // super_admin-only routes go here
});

// Public Reference Data Endpoints
Route::get('/faculties', [ReferenceDataController::class, 'faculties']);
Route::get('/departments', [ReferenceDataController::class, 'departments']);
Route::get('/programmes', [ReferenceDataController::class, 'programmes']);
Route::get('/request-types', [ReferenceDataController::class, 'requestTypes']);
require __DIR__.'/auth.php';
