<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Models\ScanFinding;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Runs ZAP baseline against the scan target, stores raw JSON, normalizes findings, and fills a minimal AI summary JSON.
 *
 * LEGAL/ETHICAL: Only scan systems you own or have explicit written authorization to test.
 */
class RunWaveScanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    private ?string $geminiFailureReason = null;

    private ?string $groqFailureReason = null;

    public function __construct(public int $scanId) {}

    public function handle(): void
    {
        $scan = Scan::query()->find($this->scanId);
        if (! $scan) {
            return;
        }

        // Stale retried jobs can be left in the queue after a scan is already finished.
        // Skip them immediately so newer scans are not blocked behind old work.
        if (in_array($scan->status, [Scan::STATUS_COMPLETED, Scan::STATUS_FAILED], true)) {
            Log::info('WAVe-Lite: skipping stale scan job', [
                'scan_id' => $scan->id,
                'status' => $scan->status,
            ]);

            return;
        }

        $prefix = "scans/{$scan->id}";
        Storage::disk('local')->makeDirectory($prefix);
        $workAbs = Storage::disk('local')->path($prefix);

        try {
            $this->touchProgress($scan, 5, Scan::PHASE_STARTING);
            $scan = $this->refreshScanOrStop();
            if (! $scan) {
                return;
            }

            $zapRelative = "{$prefix}/zap-report.json";
            $zapAbs = Storage::disk('local')->path($zapRelative);

            if (config('wave.scan_simulate', false)) {
                $this->touchProgress($scan, 20, Scan::PHASE_ZAP_SPIDER);
                $this->writeSimulatedZapReport($zapAbs, $scan->url);
                $this->touchProgress($scan, 70, Scan::PHASE_ZAP_ACTIVE);
            } elseif ($this->dockerAvailable()) {
                $this->touchProgress($scan, 15, Scan::PHASE_ZAP_SPIDER);
                $target = $this->urlForZapContainer($scan->url);
                $image = config('wave.zap_docker_image', 'zaproxy/zap-stable');

                $result = Process::timeout($this->timeout)->run([
                    'docker', 'run', '--rm',
                    '--add-host=host.docker.internal:host-gateway',
                    '-v', "{$workAbs}:/zap/wrk/:rw",
                    $image,
                    'zap-baseline.py',
                    '-t', $target,
                    '-J', '/zap/wrk/zap-report.json',
                    '-I',
                ]);

                $exit = $result->exitCode();
                if ($exit !== null && $exit > 3) {
                    throw new \RuntimeException(
                        'ZAP baseline failed (exit '.$exit.'): '.($result->errorOutput() ?: $result->output())
                    );
                }

                if (! is_file($zapAbs)) {
                    throw new \RuntimeException(
                        'ZAP did not produce zap-report.json. Output: '.substr($result->output().$result->errorOutput(), 0, 2000)
                    );
                }

                $this->touchProgress($scan, 65, Scan::PHASE_ZAP_ACTIVE);
            } elseif ($this->localZapConfigured()) {
                $this->touchProgress($scan, 15, Scan::PHASE_ZAP_SPIDER);
                $this->runZapViaLocalApi($scan, $zapAbs, $scan->url);
                $this->touchProgress($scan, 65, Scan::PHASE_ZAP_ACTIVE);
            } else {
                throw new \RuntimeException(
                    'No ZAP backend available. Options: (1) Install Docker and pull '.config('wave.zap_docker_image', 'zaproxy/zap-stable').
                    ', (2) start ZAP in daemon mode and set ZAP_HOST and ZAP_PORT in .env (optional ZAP_API_KEY), or (3) set WAVE_SCAN_SIMULATE=true for a stub run.'
                );
            }

            $scan = $this->refreshScanOrStop();
            if (! $scan) {
                return;
            }
            $scan->zap_raw_output_path = $zapRelative;
            $scan->save();

            ScanFinding::query()->where('scan_id', $scan->id)->where('source', ScanFinding::SOURCE_ZAP)->delete();
            $this->ingestZapReport($scan, $zapAbs);

            $this->touchProgress($scan, 85, Scan::PHASE_NIKTO);
            $scan = $this->refreshScanOrStop();
            if (! $scan) {
                return;
            }
            $this->runNiktoIfConfigured($scan, $prefix);

            $this->touchProgress($scan, 89, Scan::PHASE_NUCLEI);
            $scan = $this->refreshScanOrStop();
            if (! $scan) {
                return;
            }
            $this->runNucleiIfConfigured($scan, $prefix);

            $combinedRelative = "{$prefix}/combined-summary.txt";
            $this->writeCombinedSummary($scan, Storage::disk('local')->path($combinedRelative));
            $scan->combined_raw_output_path = $combinedRelative;

            $this->touchProgress($scan, 92, Scan::PHASE_AI);
            $scan = $this->refreshScanOrStop();
            if (! $scan) {
                return;
            }
            $scan->ai_summary = $this->buildAiSummary($scan);
            $scan->status = Scan::STATUS_COMPLETED;
            $scan->phase = Scan::PHASE_COMPLETED;
            $scan->progress = 100;
            $scan->completed_at = now();
            $scan->error_message = null;
            $scan->save();

            Log::info('WAVe-Lite: scan completed', ['scan_id' => $scan->id]);
        } catch (\Throwable $e) {
            Log::error('WAVe-Lite: scan job failed', [
                'scan_id' => $scan->id ?? $this->scanId,
                'error' => $e->getMessage(),
            ]);

            $scan = Scan::query()->find($this->scanId);
            if (! $scan) {
                return;
            }
            $scan->status = Scan::STATUS_FAILED;
            $scan->phase = Scan::PHASE_FAILED;
            $scan->error_message = $e->getMessage();
            $scan->completed_at = now();
            $scan->save();
        }
    }

    private function refreshScanOrStop(): ?Scan
    {
        $scan = Scan::query()->find($this->scanId);
        if (! $scan) {
            Log::info('WAVe-Lite: stopping scan job because scan was deleted', [
                'scan_id' => $this->scanId,
            ]);
        }

        return $scan;
    }

    private function touchProgress(Scan $scan, int $progress, string $phase): void
    {
        $scan->progress = $progress;
        $scan->phase = $phase;
        $scan->save();
    }

    private function dockerAvailable(): bool
    {
        $r = Process::run(['docker', 'version']);

        return $r->successful();
    }

    private function localZapConfigured(): bool
    {
        $host = config('wave.zap_local.host');
        $port = (int) config('wave.zap_local.port', 0);

        return is_string($host) && $host !== '' && $port > 0 && $port <= 65535;
    }

    private function zapLocalBaseUrl(): string
    {
        $host = config('wave.zap_local.host');
        $port = (int) config('wave.zap_local.port', 8080);

        return 'http://'.$host.':'.$port;
    }

    /**
     * @return array<string, string>
     */
    private function zapApiQuery(array $params = []): array
    {
        $key = config('wave.zap_local.api_key');
        if (is_string($key) && $key !== '') {
            $params['apikey'] = $key;
        }

        return $params;
    }

    /**
     * Drive a running ZAP instance: new session, traditional spider, wait for passive queue, export alerts as baseline-shaped JSON.
     */
    private function runZapViaLocalApi(Scan $scan, string $zapReportPath, string $targetUrl): void
    {
        $base = $this->zapLocalBaseUrl();
        $httpTimeout = 120;
        $maxChildren = (int) config('wave.zap_local.max_children', 12);
        if ($maxChildren <= 0) {
            $maxChildren = 12;
        }
        $spiderTimeout = (int) config('wave.zap_local.spider_timeout', 240);
        if ($spiderTimeout <= 0) {
            $spiderTimeout = 240;
        }
        $pscanWait = (int) config('wave.zap_local.pscan_wait', 45);
        if ($pscanWait <= 0) {
            $pscanWait = 45;
        }

        $ping = Http::connectTimeout(5)->timeout(10)->get($base.'/JSON/core/view/version/', $this->zapApiQuery());
        if (! $ping->successful()) {
            throw new \RuntimeException(
                'ZAP API not reachable at '.$base.'. Start ZAP in daemon mode on that host/port, or fix ZAP_HOST/ZAP_PORT.'
            );
        }

        $sessionName = 'wave-scan-'.$scan->id;
        Http::timeout($httpTimeout)->get(
            $base.'/JSON/core/action/newSession/',
            $this->zapApiQuery(['name' => $sessionName, 'overwrite' => 'true'])
        );

        $spiderStart = Http::timeout($httpTimeout)->get(
            $base.'/JSON/spider/action/scan/',
            $this->zapApiQuery([
                'url' => $targetUrl,
                'recurse' => 'true',
                'maxChildren' => (string) $maxChildren,
            ])
        );
        if (! $spiderStart->successful()) {
            throw new \RuntimeException('ZAP spider could not start: '.$spiderStart->body());
        }

        $spiderScanId = $spiderStart->json('scan');
        if ($spiderScanId === null || $spiderScanId === '') {
            throw new \RuntimeException('ZAP spider response missing scan id: '.$spiderStart->body());
        }

        $deadline = time() + min(max(30, $this->timeout - 60), $spiderTimeout);
        while (time() < $deadline) {
            $st = Http::timeout(30)->get(
                $base.'/JSON/spider/view/status/',
                $this->zapApiQuery(['scanId' => (string) $spiderScanId])
            );
            $pct = (int) $st->json('status', 0);
            if ($pct >= 100) {
                break;
            }
            sleep(1);
        }
        if (time() >= $deadline) {
            throw new \RuntimeException('ZAP spider timed out before completing.');
        }

        $this->touchProgress($scan, 40, Scan::PHASE_ZAP_SPIDER);

        $pscanDeadline = time() + $pscanWait;
        while (time() < $pscanDeadline) {
            $left = Http::timeout(15)->get($base.'/JSON/pscan/view/recordsToScan/', $this->zapApiQuery());
            if (! $left->successful()) {
                sleep(1);

                continue;
            }
            $raw = $left->json('recordsToScan');
            if ($raw === null) {
                sleep(1);

                continue;
            }
            if ((int) $raw <= 0) {
                break;
            }
            sleep(1);
        }

        $allAlerts = $this->fetchZapAlertsPaginated($base, $targetUrl);
        $baselineJson = $this->zapApiAlertsToBaselineJson($targetUrl, $allAlerts);
        if (file_put_contents($zapReportPath, json_encode($baselineJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            throw new \RuntimeException('Could not write ZAP report to '.$zapReportPath);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchZapAlertsPaginated(string $base, string $targetUrl): array
    {
        $out = [];
        $start = 0;
        $page = 500;

        while (true) {
            $r = Http::timeout(120)->get(
                $base.'/JSON/core/view/alerts/',
                $this->zapApiQuery([
                    'baseurl' => $targetUrl,
                    'start' => (string) $start,
                    'count' => (string) $page,
                ])
            );
            if (! $r->successful()) {
                break;
            }
            $batch = $r->json('alerts');
            if (! is_array($batch) || $batch === []) {
                break;
            }
            foreach ($batch as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }
            if (count($batch) < $page) {
                break;
            }
            $start += $page;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $apiAlerts
     * @return array<string, mixed>
     */
    private function zapApiAlertsToBaselineJson(string $targetUrl, array $apiAlerts): array
    {
        $host = parse_url($targetUrl, PHP_URL_HOST) ?: 'unknown';
        $alerts = [];

        foreach ($apiAlerts as $a) {
            $title = (string) ($a['alert'] ?? $a['name'] ?? 'ZAP alert');
            $risk = (string) ($a['risk'] ?? $a['riskString'] ?? 'Informational');
            $desc = (string) ($a['description'] ?? $a['desc'] ?? '');
            $solution = (string) ($a['solution'] ?? '');
            $uri = (string) ($a['url'] ?? $targetUrl);
            $method = strtoupper((string) ($a['method'] ?? 'GET'));

            $alerts[] = [
                'alert' => $title,
                'riskdesc' => $risk,
                'desc' => $desc,
                'solution' => $solution,
                'instances' => [['uri' => $uri, 'method' => $method]],
            ];
        }

        return [
            '@version' => '1',
            'site' => [[
                '@host' => $host,
                '@name' => $targetUrl,
                'alerts' => $alerts,
            ]],
        ];
    }

    /**
     * Map localhost / 127.0.0.1 to a host the ZAP container can reach (Docker Desktop + Linux host-gateway).
     */
    private function urlForZapContainer(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $host = strtolower($parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $parts['host'] = 'host.docker.internal';
        }

        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$parts['host'].$port.$path.$query;
    }

    private function writeSimulatedZapReport(string $path, string $targetUrl): void
    {
        $payload = [
            '@version' => '1',
            'site' => [[
                '@host' => parse_url($targetUrl, PHP_URL_HOST) ?: 'example',
                '@name' => $targetUrl,
                'alerts' => [[
                    'alert' => 'Simulated finding (WAVE_SCAN_SIMULATE=true)',
                    'riskdesc' => 'Informational',
                    'desc' => 'No real scan ran. Disable WAVE_SCAN_SIMULATE and use Docker ZAP for real results.',
                    'solution' => 'Run with Docker and zaproxy/zap-stable, or test only authorized targets.',
                    'instances' => [['uri' => $targetUrl, 'method' => 'GET']],
                ]],
            ]],
        ];
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function ingestZapReport(Scan $scan, string $jsonPath): void
    {
        $raw = @file_get_contents($jsonPath);
        if ($raw === false) {
            return;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return;
        }

        $sites = $data['site'] ?? [];
        if (! is_array($sites)) {
            return;
        }

        foreach ($sites as $site) {
            $alerts = $site['alerts'] ?? [];
            if (! is_array($alerts)) {
                continue;
            }

            foreach ($alerts as $alert) {
                if (! is_array($alert)) {
                    continue;
                }

                $title = (string) ($alert['alert'] ?? 'ZAP alert');
                $severity = isset($alert['riskdesc']) ? (string) $alert['riskdesc'] : null;
                $description = isset($alert['desc']) ? (string) $alert['desc'] : null;
                $remediation = isset($alert['solution']) ? (string) $alert['solution'] : null;
                $endpoint = null;
                $instances = $alert['instances'] ?? [];
                if (is_array($instances) && $instances !== []) {
                    $first = $instances[0];
                    if (is_array($first) && ! empty($first['uri'])) {
                        $endpoint = (string) $first['uri'];
                    }
                }

                ScanFinding::query()->create([
                    'scan_id' => $scan->id,
                    'source' => ScanFinding::SOURCE_ZAP,
                    'tool_rule_id' => isset($alert['pluginid']) ? (string) $alert['pluginid'] : null,
                    'title' => mb_substr($title, 0, 512),
                    'severity' => $severity ? mb_substr($severity, 0, 32) : null,
                    'confidence' => isset($alert['confidence']) ? (string) $alert['confidence'] : null,
                    'normalized_severity' => $this->mapZapSeverity($severity),
                    'description' => $description,
                    'evidence' => isset($alert['evidence']) ? (string) $alert['evidence'] : null,
                    'remediation' => $remediation,
                    'endpoint' => $endpoint ? mb_substr($endpoint, 0, 2048) : null,
                    'raw_metadata' => [
                        'cweid' => isset($alert['cweid']) ? (string) $alert['cweid'] : null,
                        'wascid' => isset($alert['wascid']) ? (string) $alert['wascid'] : null,
                    ],
                ]);
            }
        }
    }

    private function writeCombinedSummary(Scan $scan, string $absolutePath): void
    {
        $niktoLine = $scan->nikto_raw_output_path
            ? 'Nikto report: '.$scan->nikto_raw_output_path
            : 'Nikto: not run (disabled, unavailable, or produced no output file).';
        $nucleiRelative = "scans/{$scan->id}/nuclei-report.jsonl";
        $nucleiLine = Storage::disk('local')->exists($nucleiRelative)
            ? 'Nuclei report: '.$nucleiRelative
            : 'Nuclei: not run (disabled, unavailable, or produced no output file).';

        $lines = [
            'WAVe-Lite combined summary',
            'Target: '.$scan->url,
            'ZAP report: '.$scan->zap_raw_output_path,
            $niktoLine,
            $nucleiLine,
        ];
        file_put_contents($absolutePath, implode("\n", $lines));
    }

    private function buildAiSummary(Scan $scan): string
    {
        $geminiSummary = $this->buildGeminiSummary($scan);
        if (is_string($geminiSummary) && $geminiSummary !== '') {
            return $geminiSummary;
        }

        $groqSummary = $this->buildGroqSummary($scan);
        if (is_string($groqSummary) && $groqSummary !== '') {
            return $groqSummary;
        }

        $failureReason = collect([$this->geminiFailureReason, $this->groqFailureReason])
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->implode(' | ');

        return $this->buildHeuristicAiSummary($scan, $failureReason !== '' ? $failureReason : null);
    }

    private function buildGeminiSummary(Scan $scan): ?string
    {
        $apiKey = config('wave.gemini.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            $this->geminiFailureReason = 'Gemini API key is not configured.';
            return null;
        }

        $configuredModel = config('wave.gemini.model', 'gemini-2.5-flash');
        $configuredModel = is_string($configuredModel) && trim($configuredModel) !== ''
            ? trim($configuredModel)
            : 'gemini-2.5-flash';
        if (str_starts_with($configuredModel, 'models/')) {
            $configuredModel = substr($configuredModel, 7);
        }
        $modelCandidates = array_values(array_unique(array_filter([
            $configuredModel,
            'gemini-2.5-flash',
            'gemini-2.5-pro',
            'gemini-2.0-flash',
            'gemini-2.0-flash-lite',
        ], fn ($m) => is_string($m) && $m !== '')));
        $timeout = (int) config('wave.gemini.timeout', 60);
        if ($timeout <= 0) {
            $timeout = 60;
        }

        $input = $this->buildSummaryInput($scan);
        $prompt = $this->buildSummaryPrompt($input);

        try {
            foreach ($modelCandidates as $model) {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.
                    rawurlencode($model).
                    ':generateContent?key='.rawurlencode($apiKey);

                $response = Http::timeout($timeout)->post($url, [
                    'contents' => [[
                        'parts' => [[
                            'text' => $prompt,
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'text/plain',
                    ],
                ]);

                if (! $response->successful()) {
                    $this->geminiFailureReason = 'Gemini request failed (model '.$model.', HTTP '.$response->status().').';
                    Log::warning('WAVe-Lite: Gemini summary request failed', [
                        'scan_id' => $scan->id,
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 1000),
                    ]);

                    continue;
                }

                $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
                $paragraph = trim($text);
                if ($paragraph !== '') {
                    $this->geminiFailureReason = null;
                    return $paragraph;
                }

                $this->geminiFailureReason = 'Gemini returned an empty response (model '.$model.').';
                Log::warning('WAVe-Lite: Gemini response was empty', [
                    'scan_id' => $scan->id,
                    'model' => $model,
                ]);
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('WAVe-Lite: Gemini summary request exception', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);
            $this->geminiFailureReason = 'Gemini request exception: '.$e->getMessage();

            return null;
        }
    }

    private function buildGroqSummary(Scan $scan): ?string
    {
        $apiKey = config('wave.groq.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            $this->groqFailureReason = 'Groq API key is not configured.';

            return null;
        }

        $model = config('wave.groq.model', 'llama-3.3-70b-versatile');
        $model = is_string($model) && trim($model) !== '' ? trim($model) : 'llama-3.3-70b-versatile';
        $timeout = (int) config('wave.groq.timeout', 60);
        if ($timeout <= 0) {
            $timeout = 60;
        }

        $input = $this->buildSummaryInput($scan);
        $prompt = $this->buildSummaryPrompt($input);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [[
                        'role' => 'user',
                        'content' => $prompt,
                    ]],
                    'temperature' => 0.2,
                ]);

            if (! $response->successful()) {
                $this->groqFailureReason = 'Groq request failed (model '.$model.', HTTP '.$response->status().').';
                Log::warning('WAVe-Lite: Groq summary request failed', [
                    'scan_id' => $scan->id,
                    'model' => $model,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 1000),
                ]);

                return null;
            }

            $text = (string) data_get($response->json(), 'choices.0.message.content', '');
            $summary = trim($text);
            if ($summary === '') {
                $this->groqFailureReason = 'Groq returned an empty response (model '.$model.').';
                Log::warning('WAVe-Lite: Groq response was empty', [
                    'scan_id' => $scan->id,
                    'model' => $model,
                ]);

                return null;
            }

            $this->groqFailureReason = null;

            return $summary;
        } catch (\Throwable $e) {
            Log::warning('WAVe-Lite: Groq summary request exception', [
                'scan_id' => $scan->id,
                'error' => $e->getMessage(),
            ]);
            $this->groqFailureReason = 'Groq request exception: '.$e->getMessage();

            return null;
        }
    }

    /**
     * @return array{target_url: string, zap_findings: array<int, array<string, mixed>>, nikto_findings: array<int, array<string, mixed>>, nuclei_findings: array<int, array<string, mixed>>}
     */
    private function buildSummaryInput(Scan $scan): array
    {
        $zapFindings = ScanFinding::query()
            ->where('scan_id', $scan->id)
            ->where('source', ScanFinding::SOURCE_ZAP)
            ->orderByDesc('id')
            ->limit(40)
            ->get(['title', 'severity', 'normalized_severity', 'endpoint', 'description'])
            ->toArray();

        $niktoFindings = ScanFinding::query()
            ->where('scan_id', $scan->id)
            ->where('source', ScanFinding::SOURCE_NIKTO)
            ->orderByDesc('id')
            ->limit(40)
            ->get(['title', 'severity', 'normalized_severity', 'endpoint', 'description'])
            ->toArray();

        $nucleiFindings = ScanFinding::query()
            ->where('scan_id', $scan->id)
            ->where('source', ScanFinding::SOURCE_NUCLEI)
            ->orderByDesc('id')
            ->limit(40)
            ->get(['title', 'severity', 'normalized_severity', 'endpoint', 'description'])
            ->toArray();

        return [
            'target_url' => $scan->url,
            'zap_findings' => $zapFindings,
            'nikto_findings' => $niktoFindings,
            'nuclei_findings' => $nucleiFindings,
        ];
    }

    /**
     * @param  array{target_url: string, zap_findings: array<int, array<string, mixed>>, nikto_findings: array<int, array<string, mixed>>, nuclei_findings: array<int, array<string, mixed>>}  $input
     */
    private function buildSummaryPrompt(array $input): string
    {
        return "You are a security analyst assistant writing for business and technical stakeholders.\n".
            "Write a detailed executive summary of 700-1200 words in plain text.\n".
            "Use 4-6 short paragraphs, not bullet points, and keep language clear but not overly simplified.\n".
            "Explain: (1) overall risk posture, (2) most important weaknesses found, (3) why they matter in real-world attack scenarios,\n".
            "(4) affected endpoints/assets, and (5) a prioritized remediation plan with immediate, short-term, and medium-term actions.\n".
            "If technical terms are used, briefly explain them in context.\n".
            "Do not invent findings or endpoints. Use only provided input and be explicit about uncertainty when data is limited.\n".
            "Return plain text only (no JSON, no bullets, no markdown, no headings).\n".
            "Input JSON:\n".json_encode($input, JSON_UNESCAPED_SLASHES);
    }

    private function buildHeuristicAiSummary(Scan $scan, ?string $geminiFailureReason = null): string
    {
        $scan->loadCount('findings');

        $severityCounts = ScanFinding::query()
            ->where('scan_id', $scan->id)
            ->selectRaw("LOWER(COALESCE(NULLIF(normalized_severity, ''), severity, 'info')) as sev, COUNT(*) as c")
            ->groupBy('sev')
            ->pluck('c', 'sev')
            ->all();

        $critical = (int) ($severityCounts['critical'] ?? 0);
        $high = (int) ($severityCounts['high'] ?? 0);
        $medium = (int) ($severityCounts['medium'] ?? 0);
        $low = (int) ($severityCounts['low'] ?? 0);
        $info = (int) ($severityCounts['info'] ?? 0);

        $zapCount = ScanFinding::query()
            ->where('scan_id', $scan->id)
            ->where('source', ScanFinding::SOURCE_ZAP)
            ->count();
        $niktoCount = ScanFinding::query()
            ->where('scan_id', $scan->id)
            ->where('source', ScanFinding::SOURCE_NIKTO)
            ->count();
        $nucleiCount = ScanFinding::query()
            ->where('scan_id', $scan->id)
            ->where('source', ScanFinding::SOURCE_NUCLEI)
            ->count();

        $topFindings = ScanFinding::query()
            ->where('scan_id', $scan->id)
            ->selectRaw('title, COUNT(*) as total')
            ->groupBy('title')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(fn (ScanFinding $f) => $f->title.' ('.$f->total.')')
            ->values()
            ->all();

        $topText = $topFindings !== []
            ? implode('; ', $topFindings)
            : 'No dominant repeated pattern was detected in this run.';

        $riskLabel = 'low';
        if ($critical > 0 || $high > 0) {
            $riskLabel = 'high';
        } elseif ($medium >= 15) {
            $riskLabel = 'moderate';
        }

        $summary = 'Automated scan completed for '.$scan->url.' with '.$scan->findings_count.' total findings. '.
            'Source coverage: ZAP='.$zapCount.', Nikto='.$niktoCount.', Nuclei='.$nucleiCount.'. '.
            'Current risk posture is '.$riskLabel.' based on severity distribution: critical='.$critical.', high='.$high.', medium='.$medium.', low='.$low.', informational='.$info.'. ';

        $summary .= 'The most repeated weaknesses in this scan are: '.$topText.'. ';

        $summary .= 'Immediate actions: first remediate all critical and high findings, then address medium findings that expose authentication, session, header, or input-validation weaknesses. '.
            'Short-term actions: harden exposed endpoints, enforce secure headers, restrict unnecessary services/routes, and retest affected URLs after each fix batch. '.
            'Verification actions: rerun the scan, compare deltas against this report, and confirm that high-impact findings do not reappear.';

        if (is_string($geminiFailureReason) && trim($geminiFailureReason) !== '') {
            $summary .= ' Note: Gemini narrative generation is unavailable right now ('.$geminiFailureReason.').';
        }

        return $summary;
    }

    private function runNiktoIfConfigured(Scan $scan, string $prefix): void
    {
        if (config('wave.scan_simulate', false) || ! config('wave.nikto.enabled', true)) {
            return;
        }

        $binary = config('wave.nikto.binary', 'nikto');
        if (! is_string($binary) || $binary === '') {
            return;
        }

        $niktoRelative = "{$prefix}/nikto-report.txt";
        $niktoAbs = Storage::disk('local')->path($niktoRelative);
        $niktoAltRelative = "{$prefix}/nikto-report.txt.txt";
        $niktoAltAbs = Storage::disk('local')->path($niktoAltRelative);
        $maxtime = config('wave.nikto.maxtime', '10m');
        $maxtime = is_string($maxtime) && $maxtime !== '' ? $maxtime : '4m';
        $defaultTimeout = $this->parseDurationToSeconds($maxtime) + 60;
        $processTimeout = (int) config('wave.nikto.process_timeout', $defaultTimeout);
        if ($processTimeout <= 0) {
            $processTimeout = $defaultTimeout;
        }
        if ($processTimeout < 60) {
            $processTimeout = 60;
        }

        $result = Process::timeout($processTimeout)->run([
            $binary,
            '-host', $scan->url,
            '-nointeractive',
            '-nocheck',
            '-maxtime', $maxtime,
            '-output', $niktoAbs,
            '-Format', 'txt',
        ]);

        $niktoOutputAbs = null;
        $niktoOutputRelative = null;
        if (is_file($niktoAbs)) {
            $niktoOutputAbs = $niktoAbs;
            $niktoOutputRelative = $niktoRelative;
        } elseif (is_file($niktoAltAbs)) {
            // Some Nikto builds append the format extension to an already suffixed filename.
            $niktoOutputAbs = $niktoAltAbs;
            $niktoOutputRelative = $niktoAltRelative;
        }

        if (! $niktoOutputAbs || ! $niktoOutputRelative) {
            Log::warning('WAVe-Lite: Nikto did not write an output file', [
                'scan_id' => $scan->id,
                'exit' => $result->exitCode(),
                'output' => substr($result->output().$result->errorOutput(), 0, 2000),
            ]);

            return;
        }

        ScanFinding::query()->where('scan_id', $scan->id)->where('source', ScanFinding::SOURCE_NIKTO)->delete();
        $this->ingestNiktoReport($scan, $niktoOutputAbs);
        $scan->nikto_raw_output_path = $niktoOutputRelative;
        $scan->save();

        if ($result->failed()) {
            Log::info('WAVe-Lite: Nikto process exited non-zero (often normal when issues are reported)', [
                'scan_id' => $scan->id,
                'exit' => $result->exitCode(),
            ]);
        }
    }

    private function runNucleiIfConfigured(Scan $scan, string $prefix): void
    {
        if (config('wave.scan_simulate', false) || ! config('wave.nuclei.enabled', false)) {
            return;
        }

        $binary = config('wave.nuclei.binary', 'nuclei');
        if (! is_string($binary) || trim($binary) === '') {
            return;
        }

        $nucleiRelative = "{$prefix}/nuclei-report.jsonl";
        $nucleiAbs = Storage::disk('local')->path($nucleiRelative);

        $timeout = (int) config('wave.nuclei.timeout', 240);
        if ($timeout <= 0) {
            $timeout = 240;
        }

        $command = [
            $binary,
            '-u', $scan->url,
            '-jsonl',
            '-silent',
            '-o', $nucleiAbs,
        ];

        $severity = config('wave.nuclei.severity');
        if (is_string($severity) && trim($severity) !== '') {
            $command[] = '-severity';
            $command[] = trim($severity);
        }

        $templates = config('wave.nuclei.templates');
        if (is_string($templates) && trim($templates) !== '') {
            $command[] = '-t';
            $command[] = trim($templates);
        }

        $result = Process::timeout($timeout)->run($command);
        if (! is_file($nucleiAbs)) {
            Log::warning('WAVe-Lite: Nuclei did not write an output file', [
                'scan_id' => $scan->id,
                'exit' => $result->exitCode(),
                'output' => substr($result->output().$result->errorOutput(), 0, 2000),
            ]);

            return;
        }

        ScanFinding::query()->where('scan_id', $scan->id)->where('source', ScanFinding::SOURCE_NUCLEI)->delete();
        $this->ingestNucleiReport($scan, $nucleiAbs);

        if ($result->failed()) {
            Log::info('WAVe-Lite: Nuclei process exited non-zero', [
                'scan_id' => $scan->id,
                'exit' => $result->exitCode(),
            ]);
        }
    }

    private function parseDurationToSeconds(string $duration): int
    {
        $value = trim(strtolower($duration));
        if ($value === '') {
            return 240;
        }

        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        if (preg_match('/^(\d+)\s*([smh])$/', $value, $m) === 1) {
            $n = (int) $m[1];
            return match ($m[2]) {
                'h' => $n * 3600,
                'm' => $n * 60,
                default => $n,
            };
        }

        return 240;
    }

    private function ingestNucleiReport(Scan $scan, string $jsonlPath): void
    {
        $raw = @file_get_contents($jsonlPath);
        if ($raw === false || trim($raw) === '') {
            return;
        }

        $inserted = 0;
        foreach (preg_split("/(\r\n|\n|\r)/", $raw) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (! is_array($row)) {
                continue;
            }

            $info = is_array($row['info'] ?? null) ? $row['info'] : [];
            $severity = (string) ($info['severity'] ?? 'info');
            $title = (string) ($info['name'] ?? $row['template-id'] ?? 'Nuclei finding');
            $description = (string) ($info['description'] ?? '');
            $endpoint = (string) ($row['matched-at'] ?? $row['host'] ?? $scan->url);
            $evidence = (string) ($row['matcher-name'] ?? $row['type'] ?? '');
            $references = $info['reference'] ?? null;

            ScanFinding::query()->create([
                'scan_id' => $scan->id,
                'source' => ScanFinding::SOURCE_NUCLEI,
                'tool_rule_id' => isset($row['template-id']) ? (string) $row['template-id'] : null,
                'title' => mb_substr($title !== '' ? $title : 'Nuclei finding', 0, 512),
                'severity' => mb_substr($severity, 0, 32),
                'confidence' => null,
                'normalized_severity' => $this->mapNiktoSeverity($severity),
                'description' => $description !== '' ? $description : null,
                'evidence' => $evidence !== '' ? $evidence : null,
                'remediation' => null,
                'endpoint' => $endpoint !== '' ? mb_substr($endpoint, 0, 2048) : $scan->url,
                'raw_metadata' => [
                    'references' => is_array($references) ? $references : null,
                    'tags' => $info['tags'] ?? null,
                    'raw' => $row,
                ],
            ]);
            $inserted++;
        }

        Log::info('WAVe-Lite: Nuclei findings ingested', [
            'scan_id' => $scan->id,
            'count' => $inserted,
            'path' => $jsonlPath,
        ]);
    }

    private function ingestNiktoReport(Scan $scan, string $textPath): void
    {
        $raw = @file_get_contents($textPath);
        if ($raw === false || $raw === '') {
            return;
        }

        $origin = $this->originFromUrl($scan->url);
        $inserted = 0;

        foreach (explode("\n", $raw) as $line) {
            $line = rtrim($line, "\r");
            if ($line === '' || str_starts_with($line, '- Nikto')) {
                continue;
            }

            if (preg_match('/^\+\s+Target\s+(Host|Port):/i', $line)) {
                continue;
            }

            if (preg_match('/^\+\s+Server:\s*(.+)$/', $line, $m)) {
                $msg = trim($m[1]);
                if ($msg !== '') {
                    ScanFinding::query()->create([
                        'scan_id' => $scan->id,
                        'source' => ScanFinding::SOURCE_NIKTO,
                        'tool_rule_id' => null,
                        'title' => mb_substr('Server banner: '.$msg, 0, 512),
                        'severity' => 'Informational',
                        'confidence' => null,
                        'normalized_severity' => $this->mapNiktoSeverity('Informational'),
                        'description' => $msg,
                        'evidence' => $line,
                        'remediation' => null,
                        'endpoint' => $scan->url,
                        'raw_metadata' => [
                            'nikto_line' => $line,
                        ],
                    ]);
                    $inserted++;
                }

                continue;
            }

            if (preg_match('/^\+\s+(GET|POST|PUT|DELETE|HEAD|OPTIONS|PATCH)\s+([^:]+):\s*(.+)$/i', $line, $m)) {
                $pathPart = trim($m[2]);
                $title = trim($m[3]);
                $endpoint = null;
                if ($pathPart !== '' && str_starts_with($pathPart, '/')) {
                    $endpoint = $origin.$pathPart;
                }

                if ($title !== '') {
                    ScanFinding::query()->create([
                        'scan_id' => $scan->id,
                        'source' => ScanFinding::SOURCE_NIKTO,
                        'tool_rule_id' => null,
                        'title' => mb_substr($title, 0, 512),
                        'severity' => 'Informational',
                        'confidence' => null,
                        'normalized_severity' => $this->mapNiktoSeverity('Informational'),
                        'description' => $line,
                        'evidence' => $line,
                        'remediation' => null,
                        'endpoint' => $endpoint ? mb_substr($endpoint, 0, 2048) : $scan->url,
                        'raw_metadata' => [
                            'nikto_line' => $line,
                            'method' => strtoupper((string) $m[1]),
                        ],
                    ]);
                    $inserted++;
                }

                continue;
            }

            if (! preg_match('/^\+\s+\[(\d+)\]\s+(.+)$/', $line, $m)) {
                if (preg_match('/^\+\s+(.+?):\s*(.+)$/', $line, $generic)) {
                    $left = trim($generic[1]);
                    $right = trim($generic[2]);
                    if ($right !== '' && ! str_starts_with(strtolower($left), 'target ')) {
                        ScanFinding::query()->create([
                            'scan_id' => $scan->id,
                            'source' => ScanFinding::SOURCE_NIKTO,
                            'tool_rule_id' => null,
                            'title' => mb_substr($right, 0, 512),
                            'severity' => 'Informational',
                            'confidence' => null,
                            'normalized_severity' => $this->mapNiktoSeverity('Informational'),
                            'description' => $line,
                            'evidence' => $line,
                            'remediation' => null,
                            'endpoint' => $scan->url,
                            'raw_metadata' => [
                                'nikto_line' => $line,
                                'prefix' => $left,
                            ],
                        ]);
                        $inserted++;
                    }
                }

                continue;
            }

            $tail = $m[2];
            $colon = strpos($tail, ': ');
            if ($colon === false) {
                $title = $tail;
                $endpoint = null;
            } else {
                $pathPart = substr($tail, 0, $colon);
                $title = substr($tail, $colon + 2);
                $endpoint = null;
                if (str_starts_with($pathPart, '/')) {
                    $endpoint = $origin.$pathPart;
                }
            }

            if ($title === '') {
                continue;
            }

            ScanFinding::query()->create([
                'scan_id' => $scan->id,
                'source' => ScanFinding::SOURCE_NIKTO,
                'tool_rule_id' => (string) $m[1],
                'title' => mb_substr($title, 0, 512),
                'severity' => 'Informational',
                'confidence' => null,
                'normalized_severity' => $this->mapNiktoSeverity('Informational'),
                'description' => $line,
                'evidence' => $line,
                'remediation' => null,
                'endpoint' => $endpoint ? mb_substr($endpoint, 0, 2048) : null,
                'raw_metadata' => [
                    'nikto_line' => $line,
                ],
            ]);
            $inserted++;
        }

        Log::info('WAVe-Lite: Nikto findings ingested', [
            'scan_id' => $scan->id,
            'count' => $inserted,
            'path' => $textPath,
        ]);
    }

    private function mapZapSeverity(?string $severity): ?string
    {
        if (! is_string($severity) || trim($severity) === '') {
            return null;
        }

        return match (strtolower(trim($severity))) {
            'critical' => 'critical',
            'high' => 'high',
            'medium', 'moderate' => 'medium',
            'low' => 'low',
            default => 'info',
        };
    }

    private function mapNiktoSeverity(?string $severity): string
    {
        if (! is_string($severity) || trim($severity) === '') {
            return 'info';
        }

        return match (strtolower(trim($severity))) {
            'critical' => 'critical',
            'high' => 'high',
            'medium', 'moderate' => 'medium',
            'low' => 'low',
            default => 'info',
        };
    }

    private function originFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$parts['host'].$port;
    }
}
