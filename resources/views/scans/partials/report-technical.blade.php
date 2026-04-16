@php
    $severityStyle = function (?string $severity): string {
        return match (strtolower((string) $severity)) {
            'critical' => 'bg-red-100 text-red-800 border border-red-200',
            'high' => 'bg-orange-100 text-orange-800 border border-orange-200',
            'medium' => 'bg-yellow-100 text-yellow-800 border border-yellow-200',
            'low' => 'bg-blue-100 text-blue-800 border border-blue-200',
            default => 'bg-gray-100 text-gray-700 border border-gray-200',
        };
    };
@endphp

<div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h3 class="text-lg font-medium text-gray-900">{{ __('Technical findings') }}</h3>
    </div>
    <div class="px-6 py-5 text-sm space-y-6">
        <div>
            <h4 class="font-semibold text-gray-900">{{ __('ZAP findings') }}</h4>
            @if ($zapFindings->isEmpty())
                <p class="text-gray-500 mt-2">{{ __('No normalized ZAP findings stored yet.') }}</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($zapFindings->take(30) as $f)
                        @php $sev = strtolower((string) ($f->normalized_severity ?? $f->severity ?? 'info')); @endphp
                        <li class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-900">{{ $f->title }}</span>
                                <span class="text-xs rounded-full px-2 py-0.5 {{ $severityStyle($sev) }}">{{ strtoupper($sev) }}</span>
                            </div>
                            @if ($f->endpoint)
                                <p class="text-xs text-gray-500 break-all mt-1">{{ $f->endpoint }}</p>
                            @endif
                            @if ($f->tool_rule_id || $f->confidence)
                                <p class="mt-1 text-xs text-gray-500">
                                    @if ($f->tool_rule_id){{ __('Rule') }}: {{ $f->tool_rule_id }}@endif
                                    @if ($f->tool_rule_id && $f->confidence) · @endif
                                    @if ($f->confidence){{ __('Confidence') }}: {{ $f->confidence }}@endif
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h4 class="font-semibold text-gray-900">{{ __('Nikto findings') }}</h4>
            @if ($niktoFindings->isEmpty())
                <p class="text-gray-500 mt-2">{{ __('No normalized Nikto findings stored yet.') }}</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($niktoFindings->take(30) as $f)
                        @php $sev = strtolower((string) ($f->normalized_severity ?? $f->severity ?? 'info')); @endphp
                        <li class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-900">{{ $f->title }}</span>
                                <span class="text-xs rounded-full px-2 py-0.5 {{ $severityStyle($sev) }}">{{ strtoupper($sev) }}</span>
                            </div>
                            @if ($f->endpoint)
                                <p class="text-xs text-gray-500 break-all mt-1">{{ $f->endpoint }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

<div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h3 class="text-lg font-medium text-gray-900">{{ __('AI advisory') }}</h3>
    </div>
    <div class="px-6 py-5 text-sm space-y-3">
        <p class="rounded-md bg-indigo-50 border border-indigo-100 px-3 py-2 text-indigo-900">{{ __('Gemini only explains, summarizes, and prioritizes findings. Scanning is done by ZAP and Nikto.') }}</p>
        @if (! empty($aiSummaryText))
            <p class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-gray-700 whitespace-pre-wrap">{{ $aiSummaryText }}</p>
        @else
            <p class="text-gray-500">{{ __('No AI summary yet.') }}</p>
        @endif
    </div>
</div>
