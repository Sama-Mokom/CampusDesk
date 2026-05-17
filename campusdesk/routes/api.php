<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RequestStageController;
use App\Http\Controllers\RequestController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['auth:sanctum', 'student'])->group(function () {
    // student-only routes go here
    Route::get('/requests', [RequestController::class, 'index']);
    Route::post('/requests', [RequestController::class, 'store']);
    Route::get('/request/{requests}', [RequestController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'staff'])->group(function () {
    // staff-only routes go here
    Route::get('/requests/{request}/stages', [RequestStageController::class, 'index']);
    // Route::prefix('requests/{request}/stages/{stage}')->group(function (){
    //     Route::post('claim', [RequestStageController::class, 'claim']);
    //     Route::patch('resolve', [RequestStageController::class, 'resolve']);
    // });
    Route::post('requests/{docRequest}/stages/{stage}/claim', [RequestStageController::class, 'claim']);
    Route::patch('requests/{docRequest}/stages/{stage}/resolve', [RequestStageController::class, 'resolve']);
});
Route::middleware(['auth:sanctum', 'dept_admin'])->group(function () {
    // dept_admin routes go here
});

Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    // super_admin-only routes go here
});
require __DIR__.'/auth.php';
