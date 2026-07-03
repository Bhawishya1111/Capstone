<?php

namespace App\Http\Controllers;

use App\Jobs\RunWaveScanJob;
use App\Models\Scan;
use App\Models\ScanFinding;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $avgDurationExpression = $this->scanDurationSecondsExpression(DB::connection()->getDriverName());

        $stats = [
            'total_scans' => (clone $scansQuery)->count(),
            'queued_scans' => (clone $scansQuery)->where('status', Scan::STATUS_QUEUED)->count(),
            'running_scans' => (clone $scansQuery)->where('status', Scan::STATUS_RUNNING)->count(),
            'completed_scans' => $completedScansCount,
            'failed_scans' => $failedScansCount,
            'success_rate' => $terminalCount > 0 ? round(($completedScansCount / $terminalCount) * 100, 1) : null,
            'avg_duration_seconds' => (int) ((clone $completedQuery)
                ->whereIn('status', [Scan::STATUS_COMPLETED, Scan::STATUS_FAILED])
                ->selectRaw("AVG({$avgDurationExpression}) as avg_seconds")
                ->value('avg_seconds') ?? 0),
            'last_scan_at' => (clone $scansQuery)->max('created_at'),
        ];

        $statusBreakdown = [
            Scan::STATUS_QUEUED => (clone $scansQuery)->where('status', Scan::STATUS_QUEUED)->count(),
            Scan::STATUS_RUNNING => (clone $scansQuery)->where('status', Scan::STATUS_RUNNING)->count(),
            Scan::STATUS_COMPLETED => $completedScansCount,
            Scan::STATUS_FAILED => $failedScansCount,
        ];

        $visibleScanIds = Scan::query()->visibleToUser($user)->select('id');
        $severityCounts = ScanFinding::query()
            ->whereIn('scan_id', $visibleScanIds)
            ->selectRaw('LOWER(COALESCE(NULLIF(normalized_severity, \'\'), severity, \'info\')) as sev, COUNT(*) as c')
            ->groupBy('sev')
            ->pluck('c', 'sev')
            ->all();

        $riskSummary = [
            'critical' => (int) ($severityCounts['critical'] ?? 0),
            'high' => (int) ($severityCounts['high'] ?? 0),
            'medium' => (int) ($severityCounts['medium'] ?? 0),
            'low' => (int) ($severityCounts['low'] ?? 0),
            'info' => (int) ($severityCounts['info'] ?? 0),
        ];
        $riskSummary['total'] = array_sum($riskSummary);

        $completedWithNikto = (clone $scansQuery)
            ->where('status', Scan::STATUS_COMPLETED)
            ->whereNotNull('nikto_raw_output_path')
            ->where('nikto_raw_output_path', '!=', '')
            ->count();

        $recentFindings = ScanFinding::query()
            ->whereIn('scan_id', Scan::query()->visibleToUser($user)->select('id'))
            ->with(['scan:id,url,created_at'])
            ->latest('id')
            ->limit(8)
            ->get(['id', 'scan_id', 'source', 'title', 'normalized_severity', 'severity', 'endpoint']);

        $latestAiScan = (clone $scansQuery)
            ->where('status', Scan::STATUS_COMPLETED)
            ->whereNotNull('ai_summary')
            ->where('ai_summary', '!=', '')
            ->orderByDesc('completed_at')
            ->first(['id', 'url', 'ai_summary', 'completed_at']);

        $dashboardAiInsight = null;
        if ($latestAiScan && is_string($latestAiScan->ai_summary)) {
            $decoded = json_decode($latestAiScan->ai_summary, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $exec = data_get($decoded, 'executive_summary');
                $dashboardAiInsight = is_string($exec) ? trim($exec) : null;
            } else {
                $dashboardAiInsight = trim($latestAiScan->ai_summary);
            }
        }

        $analyticsScans = Scan::query()
            ->visibleToUser($user)
            ->orderByDesc('created_at')
            ->limit(300)
            ->get(['id', 'user_id', 'url', 'status', 'created_at', 'completed_at', 'ai_summary', 'zap_raw_output_path', 'nikto_raw_output_path', 'nuclei_raw_output_path', 'gemini_raw_output_path', 'groq_raw_output_path']);

        $ownedTargets = $analyticsScans
            ->where('user_id', $user->id)
            ->pluck('url')
            ->filter()
            ->unique()
            ->take(8)
            ->values();

        $mostScannedDomains = $analyticsScans
            ->map(function (Scan $scan): ?string {
                $host = parse_url($scan->url, PHP_URL_HOST);

                return is_string($host) && $host !== '' ? strtolower($host) : null;
            })
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(8);

        $mostCommonVulns = ScanFinding::query()
            ->whereIn('scan_id', Scan::query()->visibleToUser($user)->select('id'))
            ->selectRaw('title, COUNT(*) as total')
            ->groupBy('title')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $findingsByScanner = ScanFinding::query()
            ->whereIn('scan_id', Scan::query()->visibleToUser($user)->select('id'))
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->all();

        $geminiUsage = [
            'total_ai_summaries' => $analyticsScans->filter(fn (Scan $s) => is_string($s->ai_summary) && trim($s->ai_summary) !== '')->count(),
            'last_24h' => $analyticsScans
                ->filter(fn (Scan $s) => $s->created_at && $s->created_at->gte(now()->subDay()) && is_string($s->ai_summary) && trim($s->ai_summary) !== '')
                ->count(),
        ];

        $pendingApprovals = null;
        if (Schema::hasColumn('users', 'is_approved')) {
            $pendingApprovals = User::query()->where('is_approved', false)->count();
        } elseif (Schema::hasColumn('users', 'approved_at')) {
            $pendingApprovals = User::query()->whereNull('approved_at')->count();
        } elseif (Schema::hasColumn('users', 'status')) {
            $pendingApprovals = User::query()->where('status', 'pending')->count();
        }

        $validationSummary = null;
        if (Schema::hasColumn('scan_findings', 'validation_status')) {
            $validationSummary = ScanFinding::query()
                ->whereIn('scan_id', Scan::query()->visibleToUser($user)->select('id'))
                ->selectRaw('validation_status, COUNT(*) as c')
                ->groupBy('validation_status')
                ->pluck('c', 'validation_status')
                ->all();
        }

        $queueDepth = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $failedJobs24h = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count()
            : null;

        $topRiskyTargets = DB::table('scan_findings as f')
            ->join('scans as s', 's.id', '=', 'f.scan_id')
            ->whereIn('s.id', Scan::query()->visibleToUser($user)->select('id'))
            ->selectRaw("
                s.url,
                COUNT(*) as findings,
                SUM(
                    CASE LOWER(COALESCE(NULLIF(f.normalized_severity, ''), f.severity, 'info'))
                        WHEN 'critical' THEN 10
                        WHEN 'high' THEN 6
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 1
                        ELSE 0
                    END
                ) as risk_score
            ")
            ->groupBy('s.url')
            ->orderByDesc('risk_score')
            ->limit(6)
            ->get();

        $riskScoreHistory = $this->buildRiskScoreHistory($analyticsScans, $user->id);

        $enabledTools = [
            'zap' => (bool) (config('wave.zap_local.host') && config('wave.zap_local.port')),
            'nikto' => (bool) config('wave.nikto.enabled', true),
            'nuclei' => (bool) config('wave.nuclei.enabled', false),
            'gemini' => (bool) config('wave.gemini.api_key'),
        ];
        $scanToolOptions = [
            ['key' => 'zap', 'label' => 'ZAP', 'enabled' => (bool) $enabledTools['zap']],
            ['key' => 'nikto', 'label' => 'Nikto', 'enabled' => (bool) $enabledTools['nikto']],
            ['key' => 'nuclei', 'label' => 'Nuclei', 'enabled' => (bool) $enabledTools['nuclei']],
        ];

        $role = strtolower((string) $user->role);

        $scans = Scan::query()
            ->visibleToUser($user)
            ->with('website')
            ->withCount('findings')
            ->withCount([
                'findings as zap_findings_count' => fn ($q) => $q->where('source', \App\Models\ScanFinding::SOURCE_ZAP),
                'findings as nikto_findings_count' => fn ($q) => $q->where('source', \App\Models\ScanFinding::SOURCE_NIKTO),
                'findings as nuclei_findings_count' => fn ($q) => $q->where('source', \App\Models\ScanFinding::SOURCE_NUCLEI),
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'statusBreakdown' => $statusBreakdown,
            'riskSummary' => $riskSummary,
            'completedWithNikto' => $completedWithNikto,
            'recentFindings' => $recentFindings,
            'dashboardAiInsight' => $dashboardAiInsight,
            'latestAiScan' => $latestAiScan,
            'dashboardRole' => $role,
            'dashboardModules' => [
                'auth_2fa_enabled' => Schema::hasColumn('users', 'two_factor_secret'),
                'user_approval_workflow' => $pendingApprovals !== null,
                'target_management_enabled' => Schema::hasTable('websites'),
            ],
            'adminMetrics' => [
                'total_users' => User::query()->count(),
                'pending_approvals' => $pendingApprovals,
                'active_scans' => $stats['queued_scans'] + $stats['running_scans'],
                'enabled_tools_count' => collect($enabledTools)->filter()->count(),
                'enabled_tools' => $enabledTools,
                'most_common_vulnerabilities' => $mostCommonVulns,
                'severity_distribution' => $riskSummary,
                'queue_depth' => $queueDepth,
                'failed_jobs_24h' => $failedJobs24h,
                'gemini_usage' => $geminiUsage,
                'false_positive_trends' => $validationSummary,
                'most_scanned_domains' => $mostScannedDomains,
                'top_risky_targets' => $topRiskyTargets,
            ],
            'analystMetrics' => [
                'owned_targets' => $ownedTargets,
                'findings_by_scanner' => $findingsByScanner,
                'top_repeated_vulnerabilities' => $mostCommonVulns,
                'raw_output_available_scans' => $analyticsScans
                    ->filter(fn (Scan $s) => ! empty($s->zap_raw_output_path) || ! empty($s->nikto_raw_output_path) || ! empty($s->nuclei_raw_output_path) || ! empty($s->gemini_raw_output_path) || ! empty($s->groq_raw_output_path))
                    ->count(),
                'raw_output_total_scans' => $analyticsScans->count(),
                'risk_score_history' => $riskScoreHistory,
                'validation_summary' => $validationSummary,
            ],
            'basicMetrics' => [
                'security_score' => max(0, 100 - (int) (
                    ($riskSummary['critical'] * 7)
                    + ($riskSummary['high'] * 4)
                    + ($riskSummary['medium'] * 2)
                    + ($riskSummary['low'] * 1)
                )),
                'risk_score_history' => $riskScoreHistory,
            ],
            'scans' => $scans,
            'scanToolOptions' => $scanToolOptions,
        ]);
    }

    private function scanDurationSecondsExpression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => "(strftime('%s', completed_at) - strftime('%s', created_at))",
            'pgsql' => 'EXTRACT(EPOCH FROM (completed_at - created_at))',
            'sqlsrv' => 'DATEDIFF(second, created_at, completed_at)',
            default => 'TIMESTAMPDIFF(SECOND, created_at, completed_at)',
        };
    }

    /**
     * @return array<int, array{label: string, score: int}>
     */
    private function buildRiskScoreHistory(Collection $analyticsScans, int $userId): array
    {
        $scanIds = $analyticsScans
            ->filter(fn (Scan $s) => $s->status === Scan::STATUS_COMPLETED)
            ->sortBy('created_at')
            ->pluck('id')
            ->take(8)
            ->values();

        if ($scanIds->isEmpty()) {
            return [];
        }

        $scores = ScanFinding::query()
            ->whereIn('scan_id', $scanIds)
            ->selectRaw("
                scan_id,
                SUM(
                    CASE LOWER(COALESCE(NULLIF(normalized_severity, ''), severity, 'info'))
                        WHEN 'critical' THEN 10
                        WHEN 'high' THEN 6
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 1
                        ELSE 0
                    END
                ) as risk_score
            ")
            ->groupBy('scan_id')
            ->pluck('risk_score', 'scan_id');

        return $scanIds
            ->map(function (int $scanId) use ($analyticsScans, $scores): array {
                /** @var Scan|null $scan */
                $scan = $analyticsScans->firstWhere('id', $scanId);

                return [
                    'label' => $scan?->created_at?->format('m-d') ?? (string) $scanId,
                    'score' => (int) ($scores[$scanId] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public function storeWebsite(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url', 'regex:/^https?:\/\//i'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', 'in:zap,nikto,nuclei'],
        ]);

        $enabledToolMap = [
            'zap' => (bool) (config('wave.zap_local.host') && config('wave.zap_local.port')),
            'nikto' => (bool) config('wave.nikto.enabled', true),
            'nuclei' => (bool) config('wave.nuclei.enabled', false),
        ];
        $requestedTools = collect($data['tools'] ?? [])
            ->map(fn ($t) => strtolower((string) $t))
            ->unique()
            ->values();
        if ($requestedTools->isEmpty()) {
            $requestedTools = collect(array_keys(array_filter($enabledToolMap)));
        }
        $selectedTools = $requestedTools
            ->filter(fn (string $t) => ($enabledToolMap[$t] ?? false) === true)
            ->values()
            ->all();
        if ($selectedTools === []) {
            return redirect()->route('dashboard')->withErrors(['scan' => 'Please choose at least one enabled scanner tool.']);
        }

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
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', 'in:zap,nikto,nuclei'],
        ]);

        $enabledToolMap = [
            'zap' => (bool) (config('wave.zap_local.host') && config('wave.zap_local.port')),
            'nikto' => (bool) config('wave.nikto.enabled', true),
            'nuclei' => (bool) config('wave.nuclei.enabled', false),
        ];
        $requestedTools = collect($data['tools'] ?? [])
            ->map(fn ($tool) => strtolower((string) $tool))
            ->unique()
            ->values();
        if ($requestedTools->isEmpty()) {
            $requestedTools = collect(array_keys(array_filter($enabledToolMap)));
        }
        $selectedTools = $requestedTools
            ->filter(fn (string $tool) => ($enabledToolMap[$tool] ?? false) === true)
            ->values()
            ->all();
        if ($selectedTools === []) {
            return redirect()->route('dashboard')->withErrors(['scan' => 'Please choose at least one enabled scanner tool.']);
        }

        try {
            $scan = DB::transaction(function () use ($user, $data, $selectedTools) {
                return Scan::query()->create([
                    'user_id' => $user->id,
                    'website_id' => null,
                    'url' => $data['url'],
                    'selected_tools' => $selectedTools,
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

        $this->cancelQueuedJobsForScan($scan->id);
        $this->deleteScanArtifacts($scan);
        $scan->delete();

        return redirect()->route('dashboard')->with('status', 'Scan deleted.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        $scans = Scan::query()->visibleToUser($user)->get();

        foreach ($scans as $scan) {
            $this->cancelQueuedJobsForScan($scan->id);
            $this->deleteScanArtifacts($scan);
            $scan->delete();
        }

        return redirect()->route('dashboard')->with('status', 'All visible scans were deleted.');
    }

    public function progress(Request $request, Scan $scan): JsonResponse
    {
        $this->ensureCanViewScan($request->user(), $scan);

        $queuePosition = null;
        if (in_array($scan->status, [Scan::STATUS_QUEUED, Scan::STATUS_RUNNING], true)) {
            $queuedScanIds = DB::table('jobs')
                ->orderBy('id')
                ->pluck('payload')
                ->map(function (string $payload): ?int {
                    return $this->extractScanIdFromQueuePayload($payload);
                })
                ->filter()
                ->values();

            $index = $queuedScanIds->search(fn (int $id) => $id === (int) $scan->id);
            if (is_int($index)) {
                $queuePosition = $index + 1;
            }
        }

        $queueNotice = null;
        if ($scan->status === Scan::STATUS_QUEUED && $queuePosition !== null) {
            $queueNotice = 'Waiting in queue (position '.$queuePosition.').';
        } elseif (
            $scan->status === Scan::STATUS_RUNNING
            && (int) $scan->progress <= 5
            && strcasecmp((string) $scan->phase, Scan::PHASE_STARTING) === 0
            && $queuePosition !== null
        ) {
            $queueNotice = 'Worker is preparing this scan (position '.$queuePosition.').';
        }

        return response()->json([
            'id' => $scan->id,
            'status' => $scan->status,
            'progress' => $scan->progress,
            'phase' => $scan->phase,
            'queue_position' => $queuePosition,
            'queue_notice' => $queueNotice,
            'url' => $scan->url,
            'completed_at' => $scan->completed_at?->toIso8601String(),
            'error_message' => $scan->error_message,
        ]);
    }

    private function extractScanIdFromQueuePayload(string $payload): ?int
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return null;
        }

        $command = data_get($decoded, 'data.command');
        if (! is_string($command) || $command === '') {
            return null;
        }

        if (preg_match('/scanId";i:(\d+)/', $command, $m) !== 1) {
            return null;
        }

        return isset($m[1]) ? (int) $m[1] : null;
    }

    private function cancelQueuedJobsForScan(int $scanId): void
    {
        $jobs = DB::table('jobs')->select('id', 'payload')->get();
        $jobIdsToDelete = [];

        foreach ($jobs as $job) {
            $payload = (string) ($job->payload ?? '');
            if ($payload === '') {
                continue;
            }

            $queuedScanId = $this->extractScanIdFromQueuePayload($payload);
            if ($queuedScanId === $scanId) {
                $jobIdsToDelete[] = (int) $job->id;
            }
        }

        if ($jobIdsToDelete !== []) {
            DB::table('jobs')->whereIn('id', $jobIdsToDelete)->delete();
        }
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
        $user = $request->user();
        $this->ensureCanViewScan($user, $scan);
        $canViewTechnical = $user->canViewTechnicalReports();
        $scan->load(['website', 'findings', 'user']);
        $viewMode = $request->query('view', 'technical');
        if (! in_array($viewMode, ['technical', 'executive'], true)) {
            $viewMode = 'technical';
        }
        if (! $canViewTechnical) {
            $viewMode = 'executive';
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

        $rawAvailable = $canViewTechnical
            ? [
                'zap' => $this->rawFileExists($scan->zap_raw_output_path),
                'nikto' => $this->rawFileExists($scan->nikto_raw_output_path),
                'nuclei' => $this->rawFileExists($scan->nuclei_raw_output_path),
                'gemini' => $this->rawFileExists($scan->gemini_raw_output_path),
                'groq' => $this->rawFileExists($scan->groq_raw_output_path),
                'combined' => $this->rawFileExists($scan->combined_raw_output_path),
            ]
            : ['zap' => false, 'nikto' => false, 'nuclei' => false, 'gemini' => false, 'groq' => false, 'combined' => false];

        $trendSeries = $canViewTechnical ? $this->buildTrendSeries($scan) : [];
        $comparableScans = $canViewTechnical
            ? Scan::query()
                ->where('website_id', $scan->website_id)
                ->where('id', '!=', $scan->id)
                ->whereNotNull('completed_at')
                ->orderByDesc('completed_at')
                ->limit(20)
                ->get(['id', 'completed_at', 'status', 'url'])
            : collect();

        return view('scans.report', [
            'scan' => $scan,
            'canViewTechnical' => $canViewTechnical,
            'aiDecoded' => $aiDecoded,
            'aiSummaryText' => $aiSummaryText,
            'rawAvailable' => $rawAvailable,
            'viewMode' => $viewMode,
            'trendSeries' => $trendSeries,
            'comparableScans' => $comparableScans,
            'compareScan' => $compareScan,
            'comparison' => $canViewTechnical ? $this->buildComparison($scan, $compareScan) : null,
        ]);
    }

    /**
     * Download a raw scanner output file (paths set by Phase 3 worker under storage/app).
     */
    public function downloadRaw(Request $request, Scan $scan, string $kind): StreamedResponse
    {
        $user = $request->user();
        $this->ensureCanViewScan($user, $scan);
        if (! $user->canViewTechnicalReports()) {
            abort(403);
        }

        $column = match ($kind) {
            'zap' => 'zap_raw_output_path',
            'nikto' => 'nikto_raw_output_path',
            'nuclei' => 'nuclei_raw_output_path',
            'gemini' => 'gemini_raw_output_path',
            'groq' => 'groq_raw_output_path',
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
            $scan->nuclei_raw_output_path,
            $scan->gemini_raw_output_path,
            $scan->groq_raw_output_path,
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
