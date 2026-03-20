<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\ScanController;
use App\Models\Scan;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ScanController::class, 'dashboard'])->name('dashboard');
    Route::post('/admin/scan', [ScanController::class, 'adminScan'])->name('admin.scan');
    Route::post('/admin/websites', [ScanController::class, 'addWebsite'])->name('admin.websites');
    Route::get('/scan-progress/{id}', [ScanController::class, 'scanProgress'])->name('scan.progress');
});

Route::post('/api/update-progress', function (Request $request) {
    $scan = Scan::find($request->scan_id);

    if ($scan) {
        $scan->progress = $request->progress;
        $scan->phase = $request->phase;

        if ($request->has('status')) {
            $scan->status = $request->status;
        }

        $scan->save();
    }

    \Log::info('Progress update', $request->all());

    return response()->json(['ok' => true]);
});

require __DIR__.'/auth.php';