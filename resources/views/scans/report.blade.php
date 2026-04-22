{{-- WAVe-Lite scan report — authorized targets only --}}
@php
    $zapFindings = $scan->findings->where('source', \App\Models\ScanFinding::SOURCE_ZAP);
    $niktoFindings = $scan->findings->where('source', \App\Models\ScanFinding::SOURCE_NIKTO);
    $nucleiFindings = $scan->findings->where('source', \App\Models\ScanFinding::SOURCE_NUCLEI);
    $totalFindings = $scan->findings->count();
    $phaseText = strtolower((string) $scan->phase);
    $isTerminal = in_array($scan->status, [\App\Models\Scan::STATUS_COMPLETED, \App\Models\Scan::STATUS_FAILED], true);
    $stepState = function (string $step) use ($phaseText, $isTerminal) {
        if ($isTerminal) {
            return 'done';
        }

        return match ($step) {
            'zap' => str_contains($phaseText, 'zap') ? 'running' : (str_contains($phaseText, 'nikto') || str_contains($phaseText, 'nuclei') || str_contains($phaseText, 'ai') || str_contains($phaseText, 'completed') ? 'done' : 'pending'),
            'nikto' => str_contains($phaseText, 'nikto') ? 'running' : (str_contains($phaseText, 'nuclei') || str_contains($phaseText, 'ai') || str_contains($phaseText, 'completed') ? 'done' : 'pending'),
            'nuclei' => str_contains($phaseText, 'nuclei') ? 'running' : (str_contains($phaseText, 'ai') || str_contains($phaseText, 'completed') ? 'done' : 'pending'),
            'ai' => str_contains($phaseText, 'ai') ? 'running' : (str_contains($phaseText, 'completed') ? 'done' : 'pending'),
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

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="wl-title">
                    {{ __('Scan report') }}
                </h2>
                <p class="wl-subtitle break-all">#{{ $scan->id }} — {{ $scan->url }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="wl-btn-secondary">{{ __('← Dashboard') }}</a>
        </div>
    </x-slot>

    <div class="py-10 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="wl-card">
                <div class="wl-card-body text-sm text-amber-950 border-l-4 border-amber-300 bg-amber-50/50 rounded-2xl">
                <strong class="font-semibold">{{ __('Authorization.') }}</strong>
                {{ __('This scan must have been run only on systems you own or are authorized to test.') }}
                </div>
            </div>

            <div class="wl-card overflow-hidden border-white/20">
                <div class="wl-card-header">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Overview') }}</h3>
                </div>
                <dl class="grid grid-cols-1 gap-5 px-6 py-6 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">{{ __('Target URL') }}</dt><dd class="mt-1 font-medium text-gray-900 break-all">{{ $scan->url }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd class="mt-1 font-medium text-gray-900">{{ $scan->status }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Started') }}</dt><dd class="mt-1 text-gray-900">{{ $scan->created_at->format('Y-m-d H:i:s') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Completed') }}</dt><dd class="mt-1 text-gray-900">{{ $scan->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('Phase') }}</dt><dd class="mt-1 text-gray-900">{{ $scan->phase }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 mb-1">{{ __('Progress') }}</dt>
                        <dd>
                            <div class="h-2 w-full max-w-md overflow-hidden rounded-full bg-gray-200">
                                <div data-report-progress-bar class="h-full rounded-full bg-indigo-600 transition-all duration-300" style="width: {{ (int) $scan->progress }}%"></div>
                            </div>
                            <span data-report-progress-label class="text-xs text-gray-500 mt-1 inline-block">{{ (int) $scan->progress }}%</span>
                            <p data-report-phase-text class="mt-1 text-xs text-indigo-700">
                                {{ __('Current action:') }} <span class="font-medium">{{ $scan->phase }}</span>
                            </p>
                            <div class="mt-2 flex flex-wrap gap-1.5 text-[11px]" data-report-step-pills>
                                @php $zapState = $stepState('zap'); @endphp
                                <span data-report-step="zap" data-report-state="{{ $zapState }}" class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($zapState) }}">ZAP</span>
                                @php $niktoState = $stepState('nikto'); @endphp
                                <span data-report-step="nikto" data-report-state="{{ $niktoState }}" class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($niktoState) }}">Nikto</span>
                                @php $nucleiState = $stepState('nuclei'); @endphp
                                <span data-report-step="nuclei" data-report-state="{{ $nucleiState }}" class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($nucleiState) }}">Nuclei</span>
                                @php $aiState = $stepState('ai'); @endphp
                                <span data-report-step="ai" data-report-state="{{ $aiState }}" class="inline-flex rounded-full border px-2 py-0.5 {{ $stepClass($aiState) }}">AI</span>
                            </div>
                        </dd>
                    </div>
                    @if ($scan->error_message)
                        <div class="sm:col-span-2 rounded-md bg-red-50 border border-red-200 p-3 text-red-900">
                            <strong>{{ __('Error') }}:</strong> {{ $scan->error_message }}
                        </div>
                    @endif
                </dl>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <div class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-xl lg:col-span-3">
                    <p class="text-xs uppercase tracking-[0.16em] text-indigo-200">{{ __('Quick summary') }}</p>
                    <p class="mt-2 text-sm text-slate-200">
                        {{ __('This run produced') }} <span class="font-semibold text-white">{{ $totalFindings }}</span> {{ __('findings across ZAP, Nikto, and Nuclei. Review critical/high items first, then verify remediation with a re-scan.') }}
                    </p>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-xl">
                    <p class="text-xs text-slate-400">{{ __('Status') }}</p>
                    <p class="mt-1 text-sm font-semibold text-white">{{ strtoupper((string) $scan->status) }}</p>
                    <p class="mt-3 text-xs text-slate-400">{{ __('Progress') }}</p>
                    <p class="mt-1 text-sm font-semibold text-cyan-200">{{ (int) $scan->progress }}%</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Total findings') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalFindings }}</p>
                </div>
                <div class="rounded-2xl border border-indigo-200/80 bg-indigo-50/80 p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-indigo-700">{{ __('ZAP findings') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ $zapFindings->count() }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200/80 bg-amber-50/80 p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-amber-700">{{ __('Nikto findings') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $niktoFindings->count() }}</p>
                </div>
                <div class="rounded-2xl border border-violet-200/80 bg-violet-50/80 p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-violet-700">{{ __('Nuclei findings') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-violet-900">{{ $nucleiFindings->count() }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/80 p-5 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">{{ __('Scan duration') }}</p>
                    <p class="mt-1 text-xl font-semibold text-emerald-900">
                        @if ($scan->completed_at)
                            {{ $scan->created_at->diffAsCarbonInterval($scan->completed_at)->cascade()->forHumans(['short' => true]) }}
                        @else
                            —
                        @endif
                    </p>
                </div>
            </div>

            @if ($canViewTechnical)
                <div class="rounded-2xl border border-slate-200/80 bg-white/90 shadow-[0_1px_3px_rgba(15,23,42,0.08),0_18px_40px_rgba(15,23,42,0.06)] overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50/80 px-7 py-5">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Raw outputs') }}</h3>
                    </div>
                    <ul class="divide-y divide-slate-100 px-7 py-5 text-sm">
                        <li class="flex justify-between py-2"><span>{{ __('ZAP') }}</span>@if ($rawAvailable['zap'])<a href="{{ route('scans.downloads.raw', [$scan, 'zap']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                        <li class="flex justify-between py-2"><span>{{ __('Nikto') }}</span>@if ($rawAvailable['nikto'])<a href="{{ route('scans.downloads.raw', [$scan, 'nikto']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                        <li class="flex justify-between py-2"><span>{{ __('Nuclei') }}</span>@if ($rawAvailable['nuclei'])<a href="{{ route('scans.downloads.raw', [$scan, 'nuclei']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                        <li class="flex justify-between py-2"><span>{{ __('Gemini raw') }}</span>@if ($rawAvailable['gemini'])<a href="{{ route('scans.downloads.raw', [$scan, 'gemini']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                        <li class="flex justify-between py-2"><span>{{ __('Groq raw') }}</span>@if ($rawAvailable['groq'])<a href="{{ route('scans.downloads.raw', [$scan, 'groq']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                        <li class="flex justify-between py-2"><span>{{ __('Combined') }}</span>@if ($rawAvailable['combined'])<a href="{{ route('scans.downloads.raw', [$scan, 'combined']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                    </ul>
                </div>
            @endif

            @if ($canViewTechnical)
                <div class="sticky top-24 z-20 rounded-xl border border-white/20 bg-white/10 shadow-sm backdrop-blur-xl overflow-hidden">
                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Report view') }}</h3>
                    </div>
                    <div class="px-6 py-4 flex gap-3 text-sm">
                        <a href="{{ route('scans.report', ['scan' => $scan->id, 'view' => 'technical', 'compare_scan_id' => request('compare_scan_id')]) }}" class="rounded-md px-3 py-1.5 {{ $viewMode === 'technical' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800' }}">{{ __('Technical') }}</a>
                        <a href="{{ route('scans.report', ['scan' => $scan->id, 'view' => 'executive', 'compare_scan_id' => request('compare_scan_id')]) }}" class="rounded-md px-3 py-1.5 {{ $viewMode === 'executive' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800' }}">{{ __('Executive') }}</a>
                    </div>
                </div>
            @endif

            @if ($canViewTechnical)
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Trend and comparison') }}</h3>
                    </div>
                    <div class="px-6 py-5 space-y-6 text-sm">
                        <div>
                            <h4 class="font-semibold text-gray-900">{{ __('Recent trend (same website)') }}</h4>
                            @if (empty($trendSeries))
                                <p class="mt-2 text-gray-500">{{ __('Not enough completed scans for trend analysis yet.') }}</p>
                            @else
                                <div class="mt-2 overflow-x-auto">
                                    <table class="min-w-full text-left text-xs">
                                        <thead class="text-gray-500"><tr><th class="py-2 pr-4">#</th><th class="py-2 pr-4">{{ __('Completed') }}</th><th class="py-2 pr-4">{{ __('Status') }}</th><th class="py-2 pr-4">{{ __('Total') }}</th><th class="py-2 pr-4">{{ __('Critical') }}</th><th class="py-2 pr-4">{{ __('High') }}</th><th class="py-2 pr-4">{{ __('Medium') }}</th><th class="py-2 pr-4">{{ __('Low') }}</th><th class="py-2 pr-4">{{ __('Info') }}</th></tr></thead>
                                        <tbody class="divide-y divide-gray-100 text-gray-800">
                                        @foreach ($trendSeries as $row)
                                            <tr><td class="py-2 pr-4">{{ $row['scan_id'] }}</td><td class="py-2 pr-4">{{ $row['completed_at'] ?? '—' }}</td><td class="py-2 pr-4">{{ $row['status'] }}</td><td class="py-2 pr-4">{{ $row['total_findings'] }}</td><td class="py-2 pr-4">{{ $row['critical'] }}</td><td class="py-2 pr-4">{{ $row['high'] }}</td><td class="py-2 pr-4">{{ $row['medium'] }}</td><td class="py-2 pr-4">{{ $row['low'] }}</td><td class="py-2 pr-4">{{ $row['info'] }}</td></tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900">{{ __('Compare with previous scan') }}</h4>
                            <form method="GET" action="{{ route('scans.report', $scan) }}" class="mt-2 flex gap-2">
                                <input type="hidden" name="view" value="{{ $viewMode }}">
                                <select name="compare_scan_id" class="rounded-md border-gray-300 text-sm">
                                    <option value="">{{ __('Select scan') }}</option>
                                    @foreach ($comparableScans as $s)
                                        <option value="{{ $s->id }}" @selected($compareScan && $compareScan->id === $s->id)>#{{ $s->id }} - {{ $s->completed_at?->format('Y-m-d H:i') ?? '—' }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-white">{{ __('Compare') }}</button>
                            </form>
                            @if ($comparison)
                                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-lg border border-green-200 bg-green-50 p-4"><p class="text-xs uppercase tracking-wide font-semibold text-green-900">{{ __('New') }}</p><p class="mt-1 text-2xl font-semibold text-green-900">{{ $comparison['new_count'] }}</p></div>
                                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4"><p class="text-xs uppercase tracking-wide font-semibold text-amber-900">{{ __('Resolved') }}</p><p class="mt-1 text-2xl font-semibold text-amber-900">{{ $comparison['resolved_count'] }}</p></div>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-xs uppercase tracking-wide font-semibold text-gray-900">{{ __('Unchanged') }}</p><p class="mt-1 text-2xl font-semibold text-gray-900">{{ $comparison['unchanged_count'] }}</p></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if (! $canViewTechnical || $viewMode === 'executive')
                @include('scans.partials.report-executive', ['scan' => $scan, 'aiDecoded' => $aiDecoded, 'aiSummaryText' => $aiSummaryText])
            @else
                @include('scans.partials.report-technical', ['scan' => $scan, 'zapFindings' => $zapFindings, 'niktoFindings' => $niktoFindings, 'nucleiFindings' => $nucleiFindings, 'aiDecoded' => $aiDecoded, 'aiSummaryText' => $aiSummaryText])
            @endif
        </div>
    </div>
</x-app-layout>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const row = document.querySelector('[data-report-progress-bar]');
    if (!row) {
        return;
    }

    const bar = document.querySelector('[data-report-progress-bar]');
    const label = document.querySelector('[data-report-progress-label]');
    const phaseText = document.querySelector('[data-report-phase-text]');
    const pills = Array.from(document.querySelectorAll('[data-report-step]'));
    const pollUrl = @json(route('scans.progress', $scan));

    const classByState = {
        running: ['bg-blue-100', 'text-blue-800', 'border-blue-200'],
        done: ['bg-green-100', 'text-green-800', 'border-green-200'],
        pending: ['bg-gray-100', 'text-gray-600', 'border-gray-200'],
    };

    const determineStepState = (step, phase, status) => {
        if (status === 'completed' || status === 'failed') return 'done';
        const p = String(phase || '').toLowerCase();
        if (step === 'zap') return p.includes('zap') ? 'running' : (p.includes('nikto') || p.includes('nuclei') || p.includes('ai') || p.includes('completed') ? 'done' : 'pending');
        if (step === 'nikto') return p.includes('nikto') ? 'running' : (p.includes('nuclei') || p.includes('ai') || p.includes('completed') ? 'done' : 'pending');
        if (step === 'nuclei') return p.includes('nuclei') ? 'running' : (p.includes('ai') || p.includes('completed') ? 'done' : 'pending');
        if (step === 'ai') return p.includes('ai') ? 'running' : (p.includes('completed') ? 'done' : 'pending');
        return 'pending';
    };

    const setPillState = (el, nextState) => {
        Object.values(classByState).flat().forEach((c) => el.classList.remove(c));
        (classByState[nextState] || classByState.pending).forEach((c) => el.classList.add(c));
        el.setAttribute('data-report-state', nextState);
    };

    const applyPayload = (data) => {
        if (bar) {
            bar.style.width = `${Number(data.progress) || 0}%`;
        }
        if (label) {
            label.textContent = `${Number(data.progress) || 0}%`;
        }
        if (phaseText) {
            phaseText.innerHTML = `Current action: <span class="font-medium">${data.phase ?? '—'}</span>`;
        }
        pills.forEach((pill) => {
            const step = pill.getAttribute('data-report-step');
            const st = determineStepState(step, data.phase, data.status);
            setPillState(pill, st);
        });
    };

    const poll = async () => {
        try {
            const res = await fetch(pollUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            applyPayload(data);
            if (data.status === 'completed' || data.status === 'failed') return;
            setTimeout(poll, 3000);
        } catch (_) {
            setTimeout(poll, 4000);
        }
    };

    poll();
});
</script>
