<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\IssueActionController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\ScanController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', [ProjectController::class, 'index'])->name('dashboard');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('throttle:web-projects')->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::post('/projects/{project}/scans', [ScanController::class, 'store'])->middleware('throttle:web-scans')->name('projects.scans.store');
    Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('scans.show');
    Route::get('/scans/{scan}/status', [ScanController::class, 'status'])->name('scans.status');
    Route::post('/issues/{issue}/actions/generate-patch', [IssueActionController::class, 'generatePatch'])->name('issues.actions.generate');
    Route::post('/issues/{issue}/actions/create-pr', [IssueActionController::class, 'createPr'])->name('issues.actions.create_pr');
    Route::post('/issues/{issue}/actions/revert-pr', [IssueActionController::class, 'revertPr'])->name('issues.actions.revert_pr');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
