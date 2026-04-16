@php
    $allFindings = $scan->findings;
    $bySeverity = $allFindings
        ->groupBy(fn ($f) => strtolower((string) ($f->normalized_severity ?: $f->severity ?: 'info')))
        ->map(fn ($items) => $items->count())
        ->all();
@endphp

<div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h3 class="text-lg font-medium text-gray-900">{{ __('Executive summary') }}</h3>
    </div>
    <div class="px-6 py-5 text-sm space-y-5">
        <p class="text-gray-700">
            {{ __('This report is a non-technical summary. Automated scanners (ZAP and Nikto) identify potential risks; manual validation is still required before remediation decisions.') }}
        </p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-lg border border-red-200 bg-red-50 p-4"><p class="text-xs uppercase tracking-wide text-red-900">{{ __('Critical') }}</p><p class="mt-1 text-2xl font-semibold text-red-900">{{ $bySeverity['critical'] ?? 0 }}</p></div>
            <div class="rounded-lg border border-orange-200 bg-orange-50 p-4"><p class="text-xs uppercase tracking-wide text-orange-900">{{ __('High') }}</p><p class="mt-1 text-2xl font-semibold text-orange-900">{{ $bySeverity['high'] ?? 0 }}</p></div>
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4"><p class="text-xs uppercase tracking-wide text-yellow-900">{{ __('Medium') }}</p><p class="mt-1 text-2xl font-semibold text-yellow-900">{{ $bySeverity['medium'] ?? 0 }}</p></div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4"><p class="text-xs uppercase tracking-wide text-blue-900">{{ __('Low') }}</p><p class="mt-1 text-2xl font-semibold text-blue-900">{{ $bySeverity['low'] ?? 0 }}</p></div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4"><p class="text-xs uppercase tracking-wide text-gray-900">{{ __('Info') }}</p><p class="mt-1 text-2xl font-semibold text-gray-900">{{ $bySeverity['info'] ?? 0 }}</p></div>
        </div>

        @if (! empty($aiSummaryText))
            <div>
                <h4 class="font-semibold text-gray-900">{{ __('AI executive narrative') }}</h4>
                <p class="mt-2 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-gray-700 whitespace-pre-wrap">{{ $aiSummaryText }}</p>
            </div>
        @endif
    </div>
</div>
