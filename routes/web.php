<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [ScanController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.update-role');

    Route::post('/scans', [ScanController::class, 'startScan'])->name('scans.start');
    Route::delete('/scans', [ScanController::class, 'destroyAll'])->name('scans.destroy-all');
    Route::delete('/scans/{scan}', [ScanController::class, 'destroy'])->name('scans.destroy');
    Route::get('/scans/{scan}/progress', [ScanController::class, 'progress'])->name('scans.progress');
    Route::get('/scans/{scan}/report', [ScanController::class, 'report'])->name('scans.report');
    Route::get('/scans/{scan}/downloads/{kind}', [ScanController::class, 'downloadRaw'])
        ->where('kind', 'zap|nikto|nuclei|gemini|groq|combined')
        ->name('scans.downloads.raw');
});

Route::post('/api/scans/progress', [ScanController::class, 'updateProgress'])
    ->middleware('scanner')
    ->name('scans.progress.update');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
