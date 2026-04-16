<?php

namespace App\Http\Controllers;

use App\Jobs\RunWaveScanJob;
use App\Models\Scan;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WAVe-Lite: scan lifecycle, dashboard data, and scanner progress API.
 *
 * LEGAL/ETHICAL: Only scan systems you own or have explicit written authorization to test.
 * Unauthorized scanning may be illegal; this software does not grant permission to test third parties.
 */
class ScanController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $scansQuery = Scan::query()->visibleToUser($user);
        $completedQuery = (clone $scansQuery)->whereNotNull('completed_at');
        $completedScansCount = (clone $scansQuery)->where('status', Scan::STATUS_COMPLETED)->count();
        $failedScansCount = (clone $scansQuery)->where('status', Scan::STATUS_FAILED)->count();
        $terminalCount = $completedScansCount + $failedScansCount;

        $stats = [
            'total_scans' => (clone $scansQuery)->count(),
            'queued_scans' => (clone $scansQuery)->where('status', Scan::STATUS_QUEUED)->count(),
            'running_scans' => (clone $scansQuery)->where('status', Scan::STATUS_RUNNING)->count(),
            'completed_scans' => $completedScansCount,
            'failed_scans' => $failedScansCount,
            'success_rate' => $terminalCount > 0 ? round(($completedScansCount / $terminalCount) * 100, 1) : null,
            'avg_duration_seconds' => (int) ((clone $completedQuery)
                ->whereIn('status', [Scan::STATUS_COMPLETED, Scan::STATUS_FAILED])
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_seconds')
                ->value('avg_seconds') ?? 0),
            'last_scan_at' => (clone $scansQuery)->max('created_at'),
        ];

        $statusBreakdown = [
            Scan::STATUS_QUEUED => (clone $scansQuery)->where('status', Scan::STATUS_QUEUED)->count(),
            Scan::STATUS_RUNNING => (clone $scansQuery)->where('status', Scan::STATUS_RUNNING)->count(),
            Scan::STATUS_COMPLETED => $completedScansCount,
            Scan::STATUS_FAILED => $failedScansCount,
        ];

        $scans = Scan::query()
            ->visibleToUser($user)
            ->with('website')
            ->withCount('findings')
            ->withCount([
                'findings as zap_findings_count' => fn ($q) => $q->where('source', \App\Models\ScanFinding::SOURCE_ZAP),
                'findings as nikto_findings_count' => fn ($q) => $q->where('source', \App\Models\ScanFinding::SOURCE_NIKTO),
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'statusBreakdown' => $statusBreakdown,
            'scans' => $scans,
        ]);
    }

    public function storeWebsite(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url', 'regex:/^https?:\/\//i'],
        ]);

        Website::query()->create([
            'url' => $data['url'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('dashboard')->with('status', 'Website added.');
    }

    /**
     * Assign users to a website (admin). Enables non-admin users to run scans on that target.
     */
    public function assignWebsiteUsers(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'website_id' => ['required', 'exists:websites,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $website = Website::query()->findOrFail($data['website_id']);
        $website->assignedUsers()->sync($data['user_ids'] ?? []);

        return redirect()->route('dashboard')->with('status', 'Website access updated.');
    }

    public function startScan(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url', 'regex:/^https?:\/\//i'],
        ]);

        try {
            $scan = DB::transaction(function () use ($user, $data) {
                return Scan::query()->create([
                    'user_id' => $user->id,
                    'website_id' => null,
                    'url' => $data['url'],
                    'status' => Scan::STATUS_RUNNING,
                    'progress' => 0,
                    'phase' => Scan::PHASE_STARTING,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('WAVe-Lite: failed to create scan record', [
                'error' => $e->getMessage(),
                'url' => $data['url'] ?? null,
                'user_id' => $user->id,
            ]);

            return redirect()->route('dashboard')->withErrors(['scan' => 'Could not start scan.']);
        }

        Log::info('WAVe-Lite: scan queued', [
            'scan_id' => $scan->id,
            'url' => $scan->url,
        ]);

        $dispatch = RunWaveScanJob::dispatch($scan->id);
        $queueConnection = config('wave.scan_queue');
        if (is_string($queueConnection) && $queueConnection !== '') {
            $dispatch->onConnection($queueConnection);
        }

        return redirect()->route('dashboard')->with('status', 'Scan queued. Ensure a queue worker is running (e.g. `php artisan queue:listen` or `composer run dev`).');
    }

    public function destroy(Request $request, Scan $scan): RedirectResponse
    {
        $this->ensureCanViewScan($request->user(), $scan);

        $this->deleteScanArtifacts($scan);
        $scan->delete();

        return redirect()->route('dashboard')->with('status', 'Scan deleted.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        $scans = Scan::query()->visibleToUser($user)->get();

        foreach ($scans as $scan) {
            $this->deleteScanArtifacts($scan);
            $scan->delete();
        }

        return redirect()->route('dashboard')->with('status', 'All visible scans were deleted.');
    }

    public function progress(Request $request, Scan $scan): JsonResponse
    {
        $this->ensureCanViewScan($request->user(), $scan);

        return response()->json([
            'id' => $scan->id,
            'status' => $scan->status,
            'progress' => $scan->progress,
            'phase' => $scan->phase,
            'url' => $scan->url,
            'completed_at' => $scan->completed_at?->toIso8601String(),
            'error_message' => $scan->error_message,
        ]);
    }

    /**
     * Called by the Python scanner to update progress. Protected by scanner token middleware.
     */
    public function updateProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scan_id' => ['required', 'integer', 'exists:scans,id'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'phase' => ['required', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
            'error_message' => ['nullable', 'string', 'max:65535'],
        ]);

        $scan = Scan::query()->findOrFail($data['scan_id']);

        $scan->progress = $data['progress'];
        $scan->phase = $data['phase'];

        if (! empty($data['status'])) {
            $scan->status = $data['status'];
        }

        if (! empty($data['error_message'])) {
            $scan->error_message = $data['error_message'];
        }

        if (in_array($scan->status, [Scan::STATUS_COMPLETED, Scan::STATUS_FAILED], true)) {
            $scan->completed_at = $scan->completed_at ?? now();
        }

        $scan->save();

        Log::debug('WAVe-Lite: scan progress updated', [
            'scan_id' => $scan->id,
            'progress' => $scan->progress,
            'phase' => $scan->phase,
            'status' => $scan->status,
        ]);

        return response()->json(['ok' => true]);
    }

    public function report(Request $request, Scan $scan): View
    {
        $this->ensureCanViewScan($request->user(), $scan);
        $scan->load(['website', 'findings', 'user']);
        $viewMode = $request->query('view', 'technical');
        if (! in_array($viewMode, ['technical', 'executive'], true)) {
            $viewMode = 'technical';
        }

        $compareScanId = $request->query('compare_scan_id');
        $compareScan = null;
        if (is_numeric($compareScanId)) {
            $compareScan = Scan::query()
                ->where('id', (int) $compareScanId)
                ->where('website_id', $scan->website_id)
                ->where('id', '!=', $scan->id)
                ->first();
        }

        $aiDecoded = null;
        $aiSummaryText = null;
        if (is_string($scan->ai_summary) && $scan->ai_summary !== '') {
            $aiDecoded = json_decode($scan->ai_summary, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $aiDecoded = null;
                $aiSummaryText = trim($scan->ai_summary);
            } elseif (is_array($aiDecoded)) {
                $exec = data_get($aiDecoded, 'executive_summary');
                if (is_string($exec) && trim($exec) !== '') {
                    $aiSummaryText = trim($exec);
                }
            }
        }

        $rawAvailable = [
            'zap' => $this->rawFileExists($scan->zap_raw_output_path),
            'nikto' => $this->rawFileExists($scan->nikto_raw_output_path),
            'combined' => $this->rawFileExists($scan->combined_raw_output_path),
        ];

        $trendSeries = $this->buildTrendSeries($scan);
        $comparableScans = Scan::query()
            ->where('website_id', $scan->website_id)
            ->where('id', '!=', $scan->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(20)
            ->get(['id', 'completed_at', 'status', 'url']);

        return view('scans.report', [
            'scan' => $scan,
            'aiDecoded' => $aiDecoded,
            'aiSummaryText' => $aiSummaryText,
            'rawAvailable' => $rawAvailable,
            'viewMode' => $viewMode,
            'trendSeries' => $trendSeries,
            'comparableScans' => $comparableScans,
            'compareScan' => $compareScan,
            'comparison' => $this->buildComparison($scan, $compareScan),
        ]);
    }

    /**
     * Download a raw scanner output file (paths set by Phase 3 worker under storage/app).
     */
    public function downloadRaw(Request $request, Scan $scan, string $kind): StreamedResponse
    {
        $this->ensureCanViewScan($request->user(), $scan);

        $column = match ($kind) {
            'zap' => 'zap_raw_output_path',
            'nikto' => 'nikto_raw_output_path',
            'combined' => 'combined_raw_output_path',
            default => abort(404),
        };

        $relative = $scan->{$column};
        if (! is_string($relative) || $relative === '') {
            abort(404);
        }

        if (! Storage::disk('local')->exists($relative)) {
            Log::warning('WAVe-Lite: raw download missing file', ['scan_id' => $scan->id, 'path' => $relative]);

            abort(404);
        }

        $filename = basename($relative);

        return Storage::disk('local')->download($relative, $filename);
    }

    private function ensureAdmin(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }
    }

    private function ensureCanViewScan(User $user, Scan $scan): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $ok = Scan::query()->whereKey($scan->id)->visibleToUser($user)->exists();

        if (! $ok) {
            abort(403);
        }
    }

    private function rawFileExists(?string $relative): bool
    {
        return is_string($relative)
            && $relative !== ''
            && Storage::disk('local')->exists($relative);
    }

    private function deleteScanArtifacts(Scan $scan): void
    {
        $paths = [
            $scan->zap_raw_output_path,
            $scan->nikto_raw_output_path,
            $scan->combined_raw_output_path,
            $scan->report_path,
        ];

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        Storage::disk('local')->deleteDirectory("scans/{$scan->id}");
    }

    private function buildTrendSeries(Scan $scan): array
    {
        if (! $scan->website_id) {
            return [];
        }

        $series = Scan::query()
            ->where('website_id', $scan->website_id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->limit(20)
            ->get(['id', 'status', 'completed_at']);

        $out = [];
        foreach ($series as $item) {
            $severity = $item->findings()
                ->selectRaw("LOWER(COALESCE(normalized_severity, severity, 'info')) as sev, count(*) as c")
                ->groupBy('sev')
                ->pluck('c', 'sev')
                ->all();

            $out[] = [
                'scan_id' => $item->id,
                'completed_at' => $item->completed_at?->format('Y-m-d H:i'),
                'status' => $item->status,
                'total_findings' => array_sum(array_map('intval', $severity)),
                'critical' => (int) ($severity['critical'] ?? 0),
                'high' => (int) ($severity['high'] ?? 0),
                'medium' => (int) ($severity['medium'] ?? 0),
                'low' => (int) ($severity['low'] ?? 0),
                'info' => (int) ($severity['info'] ?? $severity['informational'] ?? 0),
            ];
        }

        return $out;
    }

    private function buildComparison(Scan $current, ?Scan $previous): ?array
    {
        if (! $previous) {
            return null;
        }

        $currentSet = $this->findingSignatureSet($current);
        $previousSet = $this->findingSignatureSet($previous);

        $new = array_values(array_diff($currentSet, $previousSet));
        $resolved = array_values(array_diff($previousSet, $currentSet));
        $unchanged = array_values(array_intersect($currentSet, $previousSet));

        return [
            'new_count' => count($new),
            'resolved_count' => count($resolved),
            'unchanged_count' => count($unchanged),
            'new_samples' => array_slice($new, 0, 10),
            'resolved_samples' => array_slice($resolved, 0, 10),
        ];
    }

    private function findingSignatureSet(Scan $scan): array
    {
        $rows = $scan->findings()
            ->get(['source', 'title', 'endpoint', 'normalized_severity', 'severity']);

        return $rows->map(function ($row) {
            $severity = $row->normalized_severity ?: $row->severity ?: 'info';

            return implode(' | ', [
                $row->source ?? '',
                $row->title ?? '',
                $row->endpoint ?? '',
                strtolower((string) $severity),
            ]);
        })->unique()->values()->all();
    }
}
