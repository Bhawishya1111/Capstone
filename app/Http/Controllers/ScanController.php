<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ScanController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            $websites = Website::latest()->get();
            $scans = Scan::latest()->get();
        } else {
            $websites = Website::latest()->get();
            $scans = Scan::where('user_id', $user->id)->latest()->get();
        }

        return view('dashboard', compact('websites', 'scans'));
    }

    public function adminScan(Request $request)
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $request->url;
        $reportRelativePath = 'storage/app/reports/zap_report.html';
        $reportAbsolutePath = storage_path('app/reports/zap_report.html');

        $scan = Scan::create([
            'user_id' => auth()->id(),
            'url' => $url,
            'status' => 'running',
            'progress' => 0,
            'phase' => 'Starting',
        ]);

        if (File::exists($reportAbsolutePath)) {
            File::delete($reportAbsolutePath);
        }

        $process = new Process([
            'python3',
            base_path('Scanner/zap_scanner.py'),
            $url,
            (string) $scan->id,
        ]);

        $process->setTimeout(600);
        $process->run();

        $output = preg_split("/\r\n|\r|\n/", trim($process->getOutput() . "\n" . $process->getErrorOutput()));
        $output = array_values(array_filter($output, fn ($line) => $line !== ''));
        $resultCode = $process->getExitCode();

        Log::info('Admin scan command executed.', [
            'command' => ['python3', base_path('Scanner/zap_scanner.py'), $url, (string) $scan->id],
            'output' => $output,
            'result_code' => $resultCode,
        ]);

        if ($resultCode === 0 && File::exists($reportAbsolutePath)) {
            $scan->update([
                'status' => 'completed',
                'progress' => 100,
                'phase' => 'Completed',
                'report_path' => $reportRelativePath,
            ]);

            return back()->with('success', 'Scan completed');
        }

        $scan->update([
            'status' => 'failed',
            'phase' => 'Failed',
            'report_path' => null,
        ]);

        return back()->with('success', 'Scan failed');
    }

    public function addWebsite(Request $request)
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        Website::create([
            'url' => $request->url,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Website added');
    }

    public function scanProgress($id)
    {
        $scan = Scan::findOrFail($id);

        if (!auth()->user()->isAdmin() && $scan->user_id !== auth()->id()) {
            abort(403);
        }

        return response()->json([
            'status' => $scan->status,
            'progress' => $scan->progress ?? 0,
            'phase' => $scan->phase ?? null,
            'report_path' => $scan->report_path,
        ]);
    }
}