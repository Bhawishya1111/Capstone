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

    $summaryText = is_string($aiSummaryText ?? null) ? trim($aiSummaryText) : '';
    $summaryPreview = $summaryText !== '' ? \Illuminate\Support\Str::limit($summaryText, 550) : '';
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
                    @foreach ($zapFindings->take(4) as $f)
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
                @if ($zapFindings->count() > 4)
                    <div class="mt-3">
                        <button
                            type="button"
                            class="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                            data-findings-toggle="zap"
                            data-findings-expanded="0"
                        >
                            {{ __('Show more') }} ({{ $zapFindings->count() - 4 }} {{ __('more') }})
                        </button>
                        <ul class="mt-3 space-y-3 hidden" data-findings-more="zap">
                            @foreach ($zapFindings->slice(4, 26) as $f)
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
                    </div>
                @endif
            @endif
        </div>

        <div>
            <h4 class="font-semibold text-gray-900">{{ __('Nikto findings') }}</h4>
            @if ($niktoFindings->isEmpty())
                <p class="text-gray-500 mt-2">{{ __('No normalized Nikto findings stored yet.') }}</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($niktoFindings->take(4) as $f)
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
                @if ($niktoFindings->count() > 4)
                    <div class="mt-3">
                        <button
                            type="button"
                            class="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                            data-findings-toggle="nikto"
                            data-findings-expanded="0"
                        >
                            {{ __('Show more') }} ({{ $niktoFindings->count() - 4 }} {{ __('more') }})
                        </button>
                        <ul class="mt-3 space-y-3 hidden" data-findings-more="nikto">
                            @foreach ($niktoFindings->slice(4, 26) as $f)
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
                    </div>
                @endif
            @endif
        </div>

        <div>
            <h4 class="font-semibold text-gray-900">{{ __('Nuclei findings') }}</h4>
            @if ($nucleiFindings->isEmpty())
                <p class="text-gray-500 mt-2">{{ __('No normalized Nuclei findings stored yet.') }}</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($nucleiFindings->take(4) as $f)
                        @php $sev = strtolower((string) ($f->normalized_severity ?? $f->severity ?? 'info')); @endphp
                        <li class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-gray-900">{{ $f->title }}</span>
                                <span class="text-xs rounded-full px-2 py-0.5 {{ $severityStyle($sev) }}">{{ strtoupper($sev) }}</span>
                            </div>
                            @if ($f->endpoint)
                                <p class="text-xs text-gray-500 break-all mt-1">{{ $f->endpoint }}</p>
                            @endif
                            @if ($f->tool_rule_id)
                                <p class="mt-1 text-xs text-gray-500">{{ __('Template') }}: {{ $f->tool_rule_id }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($nucleiFindings->count() > 4)
                    <div class="mt-3">
                        <button
                            type="button"
                            class="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                            data-findings-toggle="nuclei"
                            data-findings-expanded="0"
                        >
                            {{ __('Show more') }} ({{ $nucleiFindings->count() - 4 }} {{ __('more') }})
                        </button>
                        <ul class="mt-3 space-y-3 hidden" data-findings-more="nuclei">
                            @foreach ($nucleiFindings->slice(4, 26) as $f)
                                @php $sev = strtolower((string) ($f->normalized_severity ?? $f->severity ?? 'info')); @endphp
                                <li class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-gray-900">{{ $f->title }}</span>
                                        <span class="text-xs rounded-full px-2 py-0.5 {{ $severityStyle($sev) }}">{{ strtoupper($sev) }}</span>
                                    </div>
                                    @if ($f->endpoint)
                                        <p class="text-xs text-gray-500 break-all mt-1">{{ $f->endpoint }}</p>
                                    @endif
                                    @if ($f->tool_rule_id)
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Template') }}: {{ $f->tool_rule_id }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

<div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h3 class="text-lg font-medium text-gray-900">{{ __('AI advisory') }}</h3>
    </div>
    <div class="px-6 py-5 text-sm space-y-3">
        <p class="rounded-md bg-indigo-50 border border-indigo-100 px-3 py-2 text-indigo-900">{{ __('AI only explains, summarizes, and prioritizes findings. Scanning is done by ZAP, Nikto, and Nuclei.') }}</p>
        @if ($summaryText !== '')
            <p class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-gray-700 whitespace-pre-wrap">{{ $summaryPreview }}</p>
            @if (\Illuminate\Support\Str::length($summaryText) > \Illuminate\Support\Str::length($summaryPreview))
                <div>
                    <button
                        type="button"
                        class="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                        data-summary-toggle
                        data-summary-expanded="0"
                    >
                        {{ __('Detailed summary') }}
                    </button>
                    <p class="mt-3 hidden rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-gray-800 whitespace-pre-wrap" data-summary-full>{{ $summaryText }}</p>
                </div>
            @endif
        @else
            <p class="text-gray-500">{{ __('No AI summary yet.') }}</p>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-findings-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-findings-toggle');
            const expanded = btn.getAttribute('data-findings-expanded') === '1';
            const list = document.querySelector(`[data-findings-more="${key}"]`);
            if (!list) {
                return;
            }
            list.classList.toggle('hidden', expanded);
            btn.setAttribute('data-findings-expanded', expanded ? '0' : '1');
            btn.textContent = expanded ? 'Show more' : 'Show less';
        });
    });

    const summaryBtn = document.querySelector('[data-summary-toggle]');
    const fullSummary = document.querySelector('[data-summary-full]');
    if (summaryBtn && fullSummary) {
        summaryBtn.addEventListener('click', () => {
            const expanded = summaryBtn.getAttribute('data-summary-expanded') === '1';
            fullSummary.classList.toggle('hidden', expanded);
            summaryBtn.setAttribute('data-summary-expanded', expanded ? '0' : '1');
            summaryBtn.textContent = expanded ? 'Detailed summary' : 'Hide detailed summary';
        });
    }
});
</script>
