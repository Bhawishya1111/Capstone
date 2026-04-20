{{-- WAVe-Lite scan report — authorized targets only --}}
@php
    $zapFindings = $scan->findings->where('source', \App\Models\ScanFinding::SOURCE_ZAP);
    $niktoFindings = $scan->findings->where('source', \App\Models\ScanFinding::SOURCE_NIKTO);
    $totalFindings = $scan->findings->count();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Scan report') }}
                </h2>
                <p class="text-sm text-gray-500 font-normal break-all">#{{ $scan->id }} — {{ $scan->url }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">{{ __('← Dashboard') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <strong class="font-semibold">{{ __('Authorization.') }}</strong>
                {{ __('This scan must have been run only on systems you own or are authorized to test.') }}
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Overview') }}</h3>
                </div>
                <dl class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">{{ __('Target URL') }}</dt><dd class="mt-1 font-medium text-gray-900 break-all">{{ $scan->url }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd class="mt-1 font-medium text-gray-900">{{ $scan->status }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Started') }}</dt><dd class="mt-1 text-gray-900">{{ $scan->created_at->format('Y-m-d H:i:s') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('Completed') }}</dt><dd class="mt-1 text-gray-900">{{ $scan->completed_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('Phase') }}</dt><dd class="mt-1 text-gray-900">{{ $scan->phase }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500 mb-1">{{ __('Progress') }}</dt>
                        <dd>
                            <div class="h-2 w-full max-w-md overflow-hidden rounded-full bg-gray-200">
                                <div class="h-full rounded-full bg-indigo-600" style="width: {{ (int) $scan->progress }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500 mt-1 inline-block">{{ (int) $scan->progress }}%</span>
                        </dd>
                    </div>
                    @if ($scan->error_message)
                        <div class="sm:col-span-2 rounded-md bg-red-50 border border-red-200 p-3 text-red-900">
                            <strong>{{ __('Error') }}:</strong> {{ $scan->error_message }}
                        </div>
                    @endif
                </dl>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">{{ __('Total findings') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalFindings }}</p>
                </div>
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-indigo-700">{{ __('ZAP findings') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ $zapFindings->count() }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-amber-700">{{ __('Nikto findings') }}</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $niktoFindings->count() }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
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
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Raw outputs') }}</h3>
                    </div>
                    <ul class="divide-y divide-gray-100 px-6 py-4 text-sm">
                        <li class="flex justify-between py-2"><span>{{ __('ZAP') }}</span>@if ($rawAvailable['zap'])<a href="{{ route('scans.downloads.raw', [$scan, 'zap']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                        <li class="flex justify-between py-2"><span>{{ __('Nikto') }}</span>@if ($rawAvailable['nikto'])<a href="{{ route('scans.downloads.raw', [$scan, 'nikto']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                        <li class="flex justify-between py-2"><span>{{ __('Combined') }}</span>@if ($rawAvailable['combined'])<a href="{{ route('scans.downloads.raw', [$scan, 'combined']) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ __('Download') }}</a>@else <span class="text-gray-400">{{ __('Not available') }}</span>@endif</li>
                    </ul>
                </div>
            @endif

            @if ($canViewTechnical)
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
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
                @include('scans.partials.report-technical', ['scan' => $scan, 'zapFindings' => $zapFindings, 'niktoFindings' => $niktoFindings, 'aiDecoded' => $aiDecoded, 'aiSummaryText' => $aiSummaryText])
            @endif
        </div>
    </div>
</x-app-layout>
