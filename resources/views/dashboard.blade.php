{{--
    WAVe-Lite dashboard — authorized scanning only; see project README / UI warnings.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('WAVe-Lite') }}
                </h2>
                <p class="text-sm text-gray-500 font-normal">{{ __('Security scanning operations dashboard') }}</p>
                @php
                    $role = strtolower((string) auth()->user()?->role);
                    $rolePill = match ($role) {
                        'admin' => 'bg-red-100 text-red-800 border-red-200',
                        'analyst' => 'bg-amber-100 text-amber-800 border-amber-200',
                        default => 'bg-blue-100 text-blue-800 border-blue-200',
                    };
                @endphp
                <span class="mt-2 inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $rolePill }}">
                    {{ __('Role') }}: {{ ucfirst($role ?: 'user') }}
                </span>
            </div>
            <div class="text-xs text-gray-500">
                {{ __('Last scan:') }}
                <span class="font-medium text-gray-700">
                    {{ $stats['last_scan_at'] ? \Illuminate\Support\Carbon::parse($stats['last_scan_at'])->format('Y-m-d H:i') : '—' }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Legal / ethics — scans only on authorized targets --}}
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <strong class="font-semibold">{{ __('Authorization required.') }}</strong>
                {{ __('Run scanners only on sites, APIs, or servers you own or have explicit written permission to test. Unauthorized scanning may violate law.') }}
            </div>

            @if (session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Stats --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-gray-500">{{ __('Total scans') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ $stats['total_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-indigo-200 bg-indigo-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-indigo-800">{{ __('Queued') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-indigo-950">{{ $stats['queued_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-blue-200 bg-blue-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-blue-800">{{ __('Running') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-blue-950">{{ $stats['running_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-green-200 bg-green-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-green-800">{{ __('Completed') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-green-950">{{ $stats['completed_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-red-200 bg-red-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-red-800">{{ __('Failed') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-red-950">{{ $stats['failed_scans'] }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50/80 shadow-sm">
                    <div class="p-5">
                        <p class="text-sm font-medium text-emerald-800">{{ __('Success rate') }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-emerald-950">
                            {{ $stats['success_rate'] !== null ? $stats['success_rate'].'%' : '—' }}
                        </p>
                    </div>
                </div>
            </div>

            @if ($dashboardRole === 'admin')
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Admin overview') }}</h3>
                        <span class="text-xs text-gray-500">{{ __('System-level controls') }}</span>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 text-sm">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3"><p class="text-gray-500">{{ __('Total users') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $adminMetrics['total_users'] }}</p></div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3"><p class="text-amber-700">{{ __('Pending approvals') }}</p><p class="mt-1 text-xl font-semibold text-amber-900">{{ $adminMetrics['pending_approvals'] ?? 'Not enabled' }}</p></div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3"><p class="text-blue-700">{{ __('Active scans') }}</p><p class="mt-1 text-xl font-semibold text-blue-900">{{ $adminMetrics['active_scans'] }}</p></div>
                        <div class="rounded-lg border border-green-200 bg-green-50 p-3"><p class="text-green-700">{{ __('Enabled tools') }}</p><p class="mt-1 text-xl font-semibold text-green-900">{{ $adminMetrics['enabled_tools_count'] }}/3</p></div>
                        <div class="rounded-lg border border-purple-200 bg-purple-50 p-3"><p class="text-purple-700">{{ __('Gemini summaries') }}</p><p class="mt-1 text-xl font-semibold text-purple-900">{{ $adminMetrics['gemini_usage']['total_ai_summaries'] }}</p></div>
                    </div>
                    <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-3 text-sm">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('Most scanned domains') }}</h4>
                            <ul class="mt-2 space-y-1 text-gray-700">
                                @forelse ($adminMetrics['most_scanned_domains'] as $domain => $count)
                                    <li class="flex items-center justify-between"><span class="truncate pr-3">{{ $domain }}</span><span class="font-medium">{{ $count }}</span></li>
                                @empty
                                    <li class="text-gray-500">{{ __('No domain history yet.') }}</li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('Top risky targets') }}</h4>
                            <ul class="mt-2 space-y-1 text-gray-700">
                                @forelse ($adminMetrics['top_risky_targets'] as $target)
                                    <li class="flex items-center justify-between gap-2"><span class="truncate">{{ \Illuminate\Support\Str::limit($target->url, 36) }}</span><span class="text-xs font-medium text-red-700">{{ (int) $target->risk_score }}</span></li>
                                @empty
                                    <li class="text-gray-500">{{ __('No risk data yet.') }}</li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('System health') }}</h4>
                            <dl class="mt-2 space-y-1 text-gray-700">
                                <div class="flex justify-between"><dt>{{ __('Queue depth') }}</dt><dd class="font-medium">{{ $adminMetrics['queue_depth'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt>{{ __('Failed jobs (24h)') }}</dt><dd class="font-medium">{{ $adminMetrics['failed_jobs_24h'] ?? '—' }}</dd></div>
                                <div class="flex justify-between"><dt>{{ __('False-positive trend') }}</dt><dd class="font-medium">{{ is_array($adminMetrics['false_positive_trends']) ? 'Tracked' : 'Not enabled' }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
            @elseif ($dashboardRole === 'analyst')
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Analyst workspace') }}</h3>
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3 text-sm">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('Owned targets') }}</h4>
                            <ul class="mt-2 space-y-1 text-gray-700">
                                @forelse ($analystMetrics['owned_targets'] as $target)
                                    <li class="truncate">{{ $target }}</li>
                                @empty
                                    <li class="text-gray-500">{{ __('No owned targets yet.') }}</li>
                                @endforelse
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('Findings by scanner') }}</h4>
                            <dl class="mt-2 space-y-1 text-gray-700">
                                <div class="flex justify-between"><dt>ZAP</dt><dd class="font-medium">{{ (int) ($analystMetrics['findings_by_scanner']['zap'] ?? 0) }}</dd></div>
                                <div class="flex justify-between"><dt>Nikto</dt><dd class="font-medium">{{ (int) ($analystMetrics['findings_by_scanner']['nikto'] ?? 0) }}</dd></div>
                            </dl>
                            <p class="mt-3 text-xs text-gray-500">
                                {{ __('Raw outputs available for') }} {{ $analystMetrics['raw_output_available_scans'] }}/{{ $analystMetrics['raw_output_total_scans'] }} {{ __('scans') }}.
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('Validation status') }}</h4>
                            @if (is_array($analystMetrics['validation_summary']))
                                <ul class="mt-2 space-y-1 text-gray-700">
                                    @foreach ($analystMetrics['validation_summary'] as $state => $count)
                                        <li class="flex justify-between"><span>{{ ucfirst((string) $state) }}</span><span class="font-medium">{{ $count }}</span></li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mt-2 text-gray-500">{{ __('Confirmed / false-positive / needs-review workflow is not enabled yet.') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Website safety overview') }}</h3>
                    @php
                        $score = (int) ($basicMetrics['security_score'] ?? 0);
                        $status = $score >= 75 ? 'green' : ($score >= 45 ? 'amber' : 'red');
                        $statusClass = match ($status) {
                            'green' => 'bg-green-100 text-green-800 border-green-200',
                            'amber' => 'bg-amber-100 text-amber-800 border-amber-200',
                            default => 'bg-red-100 text-red-800 border-red-200',
                        };
                    @endphp
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <span class="inline-flex rounded-full border px-3 py-1 text-sm font-medium {{ $statusClass }}">{{ strtoupper($status) }}</span>
                        <p class="text-lg font-semibold text-gray-900">{{ __('Security score') }}: {{ $score }}/100</p>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3 text-sm">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('Top concerns') }}</h4>
                            <ol class="mt-2 list-decimal list-inside text-gray-700 space-y-1">
                                @foreach ($adminMetrics['most_common_vulnerabilities']->take(3) as $v)
                                    <li>{{ \Illuminate\Support\Str::limit($v->title, 70) }}</li>
                                @endforeach
                                @if ($adminMetrics['most_common_vulnerabilities']->isEmpty())
                                    <li>{{ __('No major concerns detected yet.') }}</li>
                                @endif
                            </ol>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('What this means') }}</h4>
                            <p class="mt-2 text-gray-700">
                                {{ __('This score helps you quickly understand the current security risk of your scanned assets. Lower score means more urgent issues need attention.') }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h4 class="font-semibold text-gray-900">{{ __('Recommended actions') }}</h4>
                            <ul class="mt-2 list-disc list-inside text-gray-700 space-y-1">
                                <li>{{ __('Fix critical and high findings first.') }}</li>
                                <li>{{ __('Re-scan after remediation to verify risk reduction.') }}</li>
                                <li>{{ __('Share executive summary with business stakeholders.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Risk posture') }}</h3>
                        <span class="text-xs text-gray-500">{{ __('From all findings you can access') }}</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-red-700">{{ __('Critical') }}</p>
                            <p class="mt-1 text-xl font-semibold text-red-900">{{ $riskSummary['critical'] }}</p>
                        </div>
                        <div class="rounded-lg border border-orange-200 bg-orange-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-orange-700">{{ __('High') }}</p>
                            <p class="mt-1 text-xl font-semibold text-orange-900">{{ $riskSummary['high'] }}</p>
                        </div>
                        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-yellow-700">{{ __('Medium') }}</p>
                            <p class="mt-1 text-xl font-semibold text-yellow-900">{{ $riskSummary['medium'] }}</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-blue-700">{{ __('Low') }}</p>
                            <p class="mt-1 text-xl font-semibold text-blue-900">{{ $riskSummary['low'] }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-gray-700">{{ __('Info') }}</p>
                            <p class="mt-1 text-xl font-semibold text-gray-900">{{ $riskSummary['info'] }}</p>
                        </div>
                    </div>
                    <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                        @php
                            $totalRisk = max(1, (int) $riskSummary['total']);
                            $criticalPct = ($riskSummary['critical'] / $totalRisk) * 100;
                            $highPct = ($riskSummary['high'] / $totalRisk) * 100;
                            $mediumPct = ($riskSummary['medium'] / $totalRisk) * 100;
                            $lowPct = ($riskSummary['low'] / $totalRisk) * 100;
                            $infoPct = ($riskSummary['info'] / $totalRisk) * 100;
                        @endphp
                        <div class="flex h-full w-full">
                            <div class="bg-red-500" style="width: {{ $criticalPct }}%"></div>
                            <div class="bg-orange-400" style="width: {{ $highPct }}%"></div>
                            <div class="bg-yellow-400" style="width: {{ $mediumPct }}%"></div>
                            <div class="bg-blue-400" style="width: {{ $lowPct }}%"></div>
                            <div class="bg-gray-400" style="width: {{ $infoPct }}%"></div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        {{ __('Nikto coverage:') }}
                        <span class="font-medium text-gray-700">{{ $completedWithNikto }}</span>
                        {{ __('of') }}
                        <span class="font-medium text-gray-700">{{ $stats['completed_scans'] }}</span>
                        {{ __('completed scans produced Nikto output.') }}
                    </p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Quick actions') }}</h3>
                    <div class="mt-4 space-y-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="block rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 font-medium text-indigo-700 hover:bg-indigo-100">{{ __('Refresh dashboard') }}</a>
                        <a href="{{ route('profile.edit') }}" class="block rounded-md border border-gray-200 bg-gray-50 px-3 py-2 font-medium text-gray-700 hover:bg-gray-100">{{ __('Manage profile') }}</a>
                        @if (auth()->user()?->isAdmin())
                            <a href="{{ route('users.index') }}" class="block rounded-md border border-amber-200 bg-amber-50 px-3 py-2 font-medium text-amber-800 hover:bg-amber-100">{{ __('Manage users & roles') }}</a>
                        @endif
                    </div>
                    <p class="mt-4 text-xs text-gray-500">{{ __('Tip: Use the report page for remediation tracking and scan comparisons.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Risk chart') }}</h3>
                    @php
                        $totalRisk = max(1, (int) $riskSummary['total']);
                        $criticalAngle = ($riskSummary['critical'] / $totalRisk) * 360;
                        $highAngle = ($riskSummary['high'] / $totalRisk) * 360;
                        $mediumAngle = ($riskSummary['medium'] / $totalRisk) * 360;
                        $lowAngle = ($riskSummary['low'] / $totalRisk) * 360;
                        $criticalEnd = $criticalAngle;
                        $highEnd = $criticalEnd + $highAngle;
                        $mediumEnd = $highEnd + $mediumAngle;
                        $lowEnd = $mediumEnd + $lowAngle;
                    @endphp
                    <div class="mt-3 inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs">
                        <button
                            type="button"
                            data-risk-toggle="pie"
                            class="rounded-md bg-white px-2.5 py-1 font-medium text-gray-800 shadow-sm"
                        >
                            {{ __('Pie') }}
                        </button>
                        <button
                            type="button"
                            data-risk-toggle="bar"
                            class="rounded-md px-2.5 py-1 font-medium text-gray-600 hover:text-gray-800"
                        >
                            {{ __('Bar') }}
                        </button>
                        <button
                            type="button"
                            data-risk-toggle="ai"
                            class="rounded-md px-2.5 py-1 font-medium text-gray-600 hover:text-gray-800"
                        >
                            {{ __('AI') }}
                        </button>
                    </div>
                    <div class="mt-4 flex flex-col items-center gap-4">
                        <div
                            data-risk-pie
                            class="h-40 w-40 rounded-full border border-gray-200"
                            style="background: conic-gradient(#ef4444 0deg {{ $criticalEnd }}deg, #fb923c {{ $criticalEnd }}deg {{ $highEnd }}deg, #facc15 {{ $highEnd }}deg {{ $mediumEnd }}deg, #60a5fa {{ $mediumEnd }}deg {{ $lowEnd }}deg, #9ca3af {{ $lowEnd }}deg 360deg);"
                            title="{{ __('Pie chart of findings by severity') }}"
                        ></div>
                        <div data-risk-bars class="hidden w-full space-y-2">
                            @php
                                $rows = [
                                    ['key' => 'critical', 'label' => __('Critical'), 'color' => 'bg-red-500'],
                                    ['key' => 'high', 'label' => __('High'), 'color' => 'bg-orange-400'],
                                    ['key' => 'medium', 'label' => __('Medium'), 'color' => 'bg-yellow-400'],
                                    ['key' => 'low', 'label' => __('Low'), 'color' => 'bg-blue-400'],
                                    ['key' => 'info', 'label' => __('Info'), 'color' => 'bg-gray-400'],
                                ];
                            @endphp
                            @foreach ($rows as $row)
                                @php
                                    $count = (int) ($riskSummary[$row['key']] ?? 0);
                                    $pct = ($count / $totalRisk) * 100;
                                @endphp
                                <div>
                                    <div class="mb-1 flex items-center justify-between text-xs">
                                        <span class="text-gray-700">{{ $row['label'] }}</span>
                                        <span class="font-medium text-gray-800">{{ $count }} ({{ number_format($pct, 1) }}%)</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full rounded-full {{ $row['color'] }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div data-risk-ai class="hidden w-full space-y-2">
                            @php
                                $dominant = collect([
                                    'critical' => (int) ($riskSummary['critical'] ?? 0),
                                    'high' => (int) ($riskSummary['high'] ?? 0),
                                    'medium' => (int) ($riskSummary['medium'] ?? 0),
                                    'low' => (int) ($riskSummary['low'] ?? 0),
                                    'info' => (int) ($riskSummary['info'] ?? 0),
                                ])->sortDesc();
                                $dominantLabel = ucfirst((string) ($dominant->keys()->first() ?? 'info'));
                                $dominantCount = (int) ($dominant->first() ?? 0);
                                $riskSummaryNarrative = 'AI chart insight: The current distribution is led by '.$dominantLabel.' findings ('.$dominantCount.'). Prioritize reducing critical and high items first, then re-scan to validate risk reduction.';
                            @endphp
                            <p class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs text-indigo-950 whitespace-pre-wrap">
                                {{ $riskSummaryNarrative }}
                            </p>
                            @if (! empty($dashboardAiInsight))
                                <p class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-700 whitespace-pre-wrap">
                                    {{ \Illuminate\Support\Str::limit((string) $dashboardAiInsight, 320) }}
                                </p>
                            @endif
                        </div>
                        <div class="grid w-full grid-cols-2 gap-2 text-xs">
                            <button type="button" data-risk-item="critical" data-risk-label="{{ __('Critical') }}" data-risk-count="{{ $riskSummary['critical'] }}" data-risk-total="{{ $totalRisk }}" class="flex items-center gap-2 text-left"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span><span>{{ __('Critical') }}: {{ $riskSummary['critical'] }}</span></button>
                            <button type="button" data-risk-item="high" data-risk-label="{{ __('High') }}" data-risk-count="{{ $riskSummary['high'] }}" data-risk-total="{{ $totalRisk }}" class="flex items-center gap-2 text-left"><span class="h-2.5 w-2.5 rounded-full bg-orange-400"></span><span>{{ __('High') }}: {{ $riskSummary['high'] }}</span></button>
                            <button type="button" data-risk-item="medium" data-risk-label="{{ __('Medium') }}" data-risk-count="{{ $riskSummary['medium'] }}" data-risk-total="{{ $totalRisk }}" class="flex items-center gap-2 text-left"><span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span><span>{{ __('Medium') }}: {{ $riskSummary['medium'] }}</span></button>
                            <button type="button" data-risk-item="low" data-risk-label="{{ __('Low') }}" data-risk-count="{{ $riskSummary['low'] }}" data-risk-total="{{ $totalRisk }}" class="flex items-center gap-2 text-left"><span class="h-2.5 w-2.5 rounded-full bg-blue-400"></span><span>{{ __('Low') }}: {{ $riskSummary['low'] }}</span></button>
                            <button type="button" data-risk-item="info" data-risk-label="{{ __('Info') }}" data-risk-count="{{ $riskSummary['info'] }}" data-risk-total="{{ $totalRisk }}" class="col-span-2 flex items-center gap-2 text-left"><span class="h-2.5 w-2.5 rounded-full bg-gray-400"></span><span>{{ __('Info') }}: {{ $riskSummary['info'] }}</span></button>
                        </div>
                        <p data-risk-hover class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                            {{ __('Hover or tap a severity to view percentage details.') }}
                        </p>
                        <div class="w-full text-xs text-gray-500">
                            {{ __('Total findings represented:') }} <span class="font-medium text-gray-700">{{ $riskSummary['total'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Gemini AI insight') }}</h3>
                        @if ($latestAiScan)
                            <a href="{{ route('scans.report', ['scan' => $latestAiScan->id, 'view' => auth()->user()?->canViewTechnicalReports() ? 'technical' : 'executive']) }}" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">
                                {{ __('From scan #') }}{{ $latestAiScan->id }}
                            </a>
                        @endif
                    </div>
                    @if (! empty($dashboardAiInsight))
                        @php
                            $aiPreview = \Illuminate\Support\Str::limit((string) $dashboardAiInsight, 520);
                            $aiHasMore = \Illuminate\Support\Str::length((string) $dashboardAiInsight) > \Illuminate\Support\Str::length($aiPreview);
                        @endphp
                        <p class="mt-3 rounded-md border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-950 whitespace-pre-wrap">{{ $aiPreview }}</p>
                        @if ($aiHasMore)
                            <button
                                type="button"
                                class="mt-2 text-sm font-medium text-indigo-700 hover:text-indigo-900"
                                data-dashboard-ai-toggle
                                data-dashboard-ai-expanded="0"
                            >
                                {{ __('Show more') }}
                            </button>
                            <p data-dashboard-ai-full class="mt-3 hidden rounded-md border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-950 whitespace-pre-wrap">{{ $dashboardAiInsight }}</p>
                        @endif
                        @if ($latestAiScan?->completed_at)
                            <p class="mt-2 text-xs text-gray-500">
                                {{ __('Generated for') }} {{ $latestAiScan->url }} · {{ $latestAiScan->completed_at->diffForHumans() }}
                            </p>
                        @endif
                    @else
                        <p class="mt-3 text-sm text-gray-500">{{ __('No Gemini insight yet. Complete a scan and this panel will show the latest AI summary.') }}</p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm xl:col-span-2">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Start scan') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Enter any authorized http(s) URL to scan.') }}</p>
                    <form method="POST" action="{{ route('scans.start') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="url" :value="__('Target URL')" />
                            <x-text-input id="url" class="block mt-1 w-full" type="url" name="url" :value="old('url')" required placeholder="https://example.com" />
                            <x-input-error :messages="$errors->get('url')" class="mt-2" />
                        </div>
                        <x-primary-button>{{ __('Start scan') }}</x-primary-button>
                    </form>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Scan health') }}</h3>
                        <form method="POST" action="{{ route('scans.destroy-all') }}" onsubmit="return confirm('Delete all scans visible to you? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                                {{ __('Clear all scans') }}
                            </button>
                        </form>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Current distribution and runtime behavior.') }}</p>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between"><dt class="text-gray-500">{{ __('Queued') }}</dt><dd class="font-medium text-indigo-700">{{ $statusBreakdown[\App\Models\Scan::STATUS_QUEUED] }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-gray-500">{{ __('Running') }}</dt><dd class="font-medium text-blue-700">{{ $statusBreakdown[\App\Models\Scan::STATUS_RUNNING] }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-gray-500">{{ __('Completed') }}</dt><dd class="font-medium text-green-700">{{ $statusBreakdown[\App\Models\Scan::STATUS_COMPLETED] }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-gray-500">{{ __('Failed') }}</dt><dd class="font-medium text-red-700">{{ $statusBreakdown[\App\Models\Scan::STATUS_FAILED] }}</dd></div>
                        <div class="pt-2 mt-2 border-t border-gray-100 flex items-center justify-between">
                            <dt class="text-gray-500">{{ __('Avg duration') }}</dt>
                            <dd class="font-medium text-gray-800">
                                @if ($stats['avg_duration_seconds'] > 0)
                                    {{ gmdate('H:i:s', (int) $stats['avg_duration_seconds']) }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-xs text-gray-500">{{ __('Tip: Keep a queue worker running to avoid stalled scans.') }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Recent findings feed') }}</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($recentFindings as $finding)
                        @php
                            $sev = strtolower((string) ($finding->normalized_severity ?: $finding->severity ?: 'info'));
                            $sevClass = match ($sev) {
                                'critical' => 'bg-red-100 text-red-800',
                                'high' => 'bg-orange-100 text-orange-800',
                                'medium' => 'bg-yellow-100 text-yellow-800',
                                'low' => 'bg-blue-100 text-blue-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <div class="px-6 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 break-words">{{ $finding->title }}</p>
                                    <p class="mt-1 text-xs text-gray-500 break-all">
                                        {{ strtoupper($finding->source) }} · {{ \Illuminate\Support\Str::limit((string) $finding->endpoint, 90) ?: 'No endpoint provided' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $sevClass }}">{{ strtoupper($sev) }}</span>
                                    @if ($finding->scan)
                                        <a href="{{ route('scans.report', ['scan' => $finding->scan->id, 'view' => auth()->user()?->canViewTechnicalReports() ? 'technical' : 'executive']) }}" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">
                                            {{ __('Scan #') }}{{ $finding->scan->id }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No findings yet.') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Scans table --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Recent scans') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Target') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Status') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Scanner steps') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Findings') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600 w-48">{{ __('Progress') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Phase') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Started') }}</th>
                                <th class="px-6 py-3 text-left font-medium text-gray-600">{{ __('Duration') }}</th>
                                <th class="px-6 py-3 text-right font-medium text-gray-600">{{ __('') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($scans as $scan)
                                @php
                                    $statusStyles = match ($scan->status) {
                                        \App\Models\Scan::STATUS_RUNNING => 'bg-blue-100 text-blue-800',
                                        \App\Models\Scan::STATUS_COMPLETED => 'bg-green-100 text-green-800',
                                        \App\Models\Scan::STATUS_FAILED => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    };
                                    $poll = $scan->status === \App\Models\Scan::STATUS_RUNNING || $scan->status === \App\Models\Scan::STATUS_QUEUED;
                                    $phase = strtolower((string) $scan->phase);
                                    $isTerminal = in_array($scan->status, [\App\Models\Scan::STATUS_COMPLETED, \App\Models\Scan::STATUS_FAILED], true);

                                    $stepState = function (string $step) use ($phase, $isTerminal) {
                                        if ($isTerminal) {
                                            return 'done';
                                        }

                                        return match ($step) {
                                            'zap' => str_contains($phase, 'zap') ? 'running' : (str_contains($phase, 'nikto') || str_contains($phase, 'ai') || str_contains($phase, 'completed') ? 'done' : 'pending'),
                                            'nikto' => str_contains($phase, 'nikto') ? 'running' : (str_contains($phase, 'nuclei') || str_contains($phase, 'ai') || str_contains($phase, 'completed') ? 'done' : 'pending'),
                                            'nuclei' => str_contains($phase, 'nuclei') ? 'running' : (str_contains($phase, 'ai') || str_contains($phase, 'completed') ? 'done' : 'pending'),
                                            'ai' => str_contains($phase, 'ai') ? 'running' : (str_contains($phase, 'completed') ? 'done' : 'pending'),
                                            default => 'pending',
                                        };
                                    };

                                    $stepClass = function (string $state) {
                                        return match ($state) {
                                            'running' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'done' => 'bg-green-100 text-green-800 border-green-200',
                                            default => 'bg-gray-100 text-gray-600 border-gray-200',
                                        };
                                    };
                                @endphp
                                <tr
                                    data-scan-row
                                    @if ($poll) data-poll-url="{{ route('scans.progress', $scan) }}" @endif
                                >
                                    <td class="px-6 py-3 font-medium text-gray-900 break-all max-w-xs">
                                        <span class="block">{{ \Illuminate\Support\Str::limit($scan->url, 56) }}</span>
                                        <span class="text-xs text-gray-500">#{{ $scan->id }}</span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span data-scan-status-badge class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusStyles }}">
                                            {{ $scan->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex flex-wrap gap-1.5 text-[11px]">
                                            @php $zapState = $stepState('zap'); @endphp
                                            <span class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($zapState) }}">ZAP</span>
                                            @php $niktoState = $stepState('nikto'); @endphp
                                            <span class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($niktoState) }}">Nikto</span>
                                            @php $nucleiState = $stepState('nuclei'); @endphp
                                            <span class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($nucleiState) }}">Nuclei</span>
                                            @php $aiState = $stepState('ai'); @endphp
                                            <span class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($aiState) }}">AI</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-xs text-gray-700 space-y-0.5">
                                            <p class="font-medium text-gray-900">{{ (int) $scan->findings_count }} {{ __('total') }}</p>
                                            <p>
                                                ZAP: {{ (int) $scan->zap_findings_count }}
                                                · Nikto: {{ (int) $scan->nikto_findings_count }}
                                                · Nuclei: {{ (int) ($scan->nuclei_findings_count ?? 0) }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                            <div
                                                data-scan-progress-bar
                                                class="h-full rounded-full bg-indigo-600 transition-all duration-300"
                                                style="width: {{ (int) $scan->progress }}%"
                                            ></div>
                                        </div>
                                        <span class="mt-1 inline-block text-xs text-gray-500">{{ (int) $scan->progress }}%</span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-700" data-scan-phase>{{ $scan->phase }}</td>
                                    <td class="px-6 py-3 text-gray-600 whitespace-nowrap" title="{{ $scan->created_at->format('Y-m-d H:i:s') }}">{{ $scan->created_at->diffForHumans() }}</td>
                                    <td class="px-6 py-3 text-gray-600 whitespace-nowrap">
                                        @if ($scan->completed_at)
                                            {{ $scan->created_at->diffAsCarbonInterval($scan->completed_at)->cascade()->forHumans(['short' => true]) }}
                                        @else
                                            —
                                        @endif
                                        <p data-scan-queue-hint class="mt-1 text-[11px] text-indigo-600"></p>
                                    </td>
                                    <td class="px-6 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('scans.report', ['scan' => $scan->id, 'view' => auth()->user()?->canViewTechnicalReports() ? 'technical' : 'executive']) }}" class="inline-flex rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-700 hover:bg-indigo-100 font-medium">
                                            {{ __('Report') }}
                                        </a>
                                        <form method="POST" action="{{ route('scans.destroy', $scan) }}" onsubmit="return confirm('Delete this scan? This cannot be undone.');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-red-700 hover:bg-red-100 font-medium">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('No scans yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        @vite(['resources/js/dashboard.js'])
    @endpush
</x-app-layout>
