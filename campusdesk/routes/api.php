<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['auth:sanctum', 'student'])->group(function () {
    // student-only routes go here
});

Route::middleware(['auth:sanctum', 'staff'])->group(function () {
    // staff-only routes go here
});

Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    // super_admin-only routes go here
});
