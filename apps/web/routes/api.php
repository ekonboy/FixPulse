<?php

use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\TechController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::middleware('throttle:api-projects')->group(function (): void {
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
    });

    Route::middleware('throttle:api-scans')->group(function (): void {
        Route::post('/projects/{project}/scans', [ScanController::class, 'store']);
        Route::get('/scans/{scan}', [ScanController::class, 'show']);
        Route::get('/scans/{scan}/issues', [ScanController::class, 'issues']);
        Route::get('/scans/{scan}/plan', [ScanController::class, 'plan']);
    });

    Route::post('/tech/analyze', [TechController::class, 'analyze'])->middleware('throttle:api-scans');
});
